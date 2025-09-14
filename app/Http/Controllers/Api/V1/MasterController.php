<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Master;
use App\Models\TelegramUser;
use App\Models\Location;
use App\Models\MasterMedia;
use Idpromogroup\LaravelOpenAIAssistants\Facades\OpenAIAssistants;
use Illuminate\Support\Facades\Log;

/**
 * ИНСТРУКЦИЯ ДЛЯ ИИ: API для добавления мастеров
 * 
 * АДРЕС: POST https://fixfox.app/api/v1/masters
 * АВТОРИЗАЦИЯ: Обязательный заголовок X-Api-Secret: Ap1_K9f!xQmZ3gR7vL2w*D8hNeYsBc4Xt9UJMa0pVEs
 * 
 * ФОРМАТ ДАННЫХ:
 * - С файлами: multipart/form-data
 * - Без файлов: application/json
 * 
 * ОБЯЗАТЕЛЬНЫЕ ПОЛЯ:
 * - text (string): описание мастера, его услуги и навыки
 * 
 * НЕОБЯЗАТЕЛЬНЫЕ ПОЛЯ:
 * - tid (integer): Telegram ID пользователя для проверки дублей
 * - username (string): Telegram @username
 * - email (string): email пользователя  
 * - phone (string): телефон пользователя
 * - name (string): имя пользователя
 * - files[] (array): массив файлов (только фото/видео, макс 50MB каждый)
 * 
 * ОТВЕТЫ API:
 * 
 * УСПЕХ (201):
 * {
 *   "status": "ok",
 *   "uid": 123,
 *   "media_count": 2
 * }
 * 
 * МАСТЕР УЖЕ СУЩЕСТВУЕТ (200):
 * {
 *   "status": "exists", 
 *   "tid": 987654321,
 *   "uid": 123
 * }
 * 
 * ОШИБКА АВТОРИЗАЦИИ (401):
 * {
 *   "error": "Unauthorized"
 * }
 * 
 * ОШИБКА ВАЛИДАЦИИ (422):
 * {
 *   "message": "The text field is required.",
 *   "errors": {...}
 * }
 * 
 * АЛГОРИТМ РАБОТЫ С API:
 * 1. Если у мастера есть tid - сначала проверить существование
 * 2. При ответе "exists" - мастер уже зарегистрирован, дублировать не нужно  
 * 3. При ответе "ok" - мастер успешно создан
 * 4. Файлы отправлять как files[], поддерживаются только изображения и видео
 * 5. Система автоматически извлечет геолокации из текста описания
 * 
 * ПРИМЕРЫ ЗАПРОСОВ:
 * 
 * С файлами:
 * curl -X POST https://fixfox.app/api/v1/masters \
 *   -H "X-Api-Secret: Ap1_K9f!xQmZ3gR7vL2w*D8hNeYsBc4Xt9UJMa0pVEs" \
 *   -F "text=Ремонт квартир в Москве" \
 *   -F "files[]=@photo1.jpg" \
 *   -F "tid=987654321"
 * 
 * Без файлов:
 * curl -X POST https://fixfox.app/api/v1/masters \
 *   -H "X-Api-Secret: Ap1_K9f!xQmZ3gR7vL2w*D8hNeYsBc4Xt9UJMa0pVEs" \
 *   -H "Content-Type: application/json" \
 *   -d '{"text": "Сантехник в СПб", "tid": 123456}'
 */

class MasterController extends Controller
{
    public function index()
    {
        return response()->json(Master::with(['media', 'locations'])->latest()->get());
    }

    public function store(Request $request)
    {
        try {
            // Отключаем debug вывод для этой сессии
            config(['openai-assistants.debug_output' => false]);
            
            // Critical validation: tid is mandatory for API master creation
            if (empty($request->input('tid'))) {
                $errorData = [
                    'request_data' => $request->all(),
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'timestamp' => now()->toISOString(),
                ];
                
                Log::error('API Master creation attempted without tid', $errorData);
                
                return response()->json([
                    'error' => 'Telegram ID (tid) is required for master creation',
                    'message' => 'tid field is mandatory'
                ], 422);
            }

            $validated = $request->validate([
                'text' => 'required|string',
                'tid' => 'required|integer',
                'username' => 'nullable|string',
                'name' => 'nullable|string',
                'phone' => 'nullable|string',
                'email' => 'nullable|email',
                'location_comm' => 'nullable|string',
                'entities' => 'nullable|json',
            ]);

            $uid = null;
            
            $existingMaster = null;
            
            // Проверка на существование по tid ЕСЛИ он передан
            if (!empty($validated['tid'])) {
                $existingTelegramUser = TelegramUser::where('tid', $validated['tid'])->first();
                if ($existingTelegramUser) {
                    $uid = $existingTelegramUser->uid;

                    $existingMaster = Master::where('uid', $existingTelegramUser->uid)->first();
                    if ($existingMaster) {
                        $master = $existingMaster;
                    };
                }
            }

            // Если uid не определен (tid не передан или не найден) - создаем нового пользователя
            if (!$uid) {
                $userData = [
                    'name' => $validated['name'] ?? '',
                ];
                
                // Добавляем email только если он не пустой
                if (!empty($validated['email'])) $userData['email'] = $validated['email'];                
                // Добавляем phone только если он не пустой
                if (!empty($validated['phone']))  $userData['phone'] = $validated['phone'];
                
                $user = \App\Models\User::create($userData);
                $uid = $user->id;
                
                // Если есть tid - создаем TelegramUser
                if (!empty($validated['tid']) && empty($existingTelegramUser)) {
                    \App\Models\TelegramUser::create([
                        'uid' => $uid,
                        'tid' => $validated['tid'],
                        'username' => $validated['username'] ?? null,
                        'name' => $validated['name'] ?? null,
                        'activity_status' => \App\Models\TelegramUser::ACTIVITY_STATUS_BLOCKED,
                    ]);
                }
            } else {
                // Обновляем существующего пользователя дополнительной информацией
                $user = \App\Models\User::find($uid);
                if ($user) {
                    $updateData = [];
                    
                    // Обновляем только если новые данные не пустые
                    if (!empty($validated['name']) && empty($user->name)) {
                        $updateData['name'] = $validated['name'];
                    }
                    if (!empty($validated['email']) && empty($user->email)) {
                        $updateData['email'] = $validated['email'];
                    }
                    if (!empty($validated['phone']) && empty($user->phone)) {
                        $updateData['phone'] = $validated['phone'];
                    }
                    
                    if (!empty($updateData)) {
                        $user->update($updateData);
                    }
                }
                
                // Обновляем TelegramUser если нужно
                if (!empty($validated['tid'])) {
                    $telegramUser = TelegramUser::where('tid', $validated['tid'])->first();
                    if ($telegramUser) {
                        $telegramUpdateData = [];
                        if (!empty($validated['username']) && empty($telegramUser->username)) {
                            $telegramUpdateData['username'] = $validated['username'];
                        }
                        if (!empty($validated['name']) && empty($telegramUser->name)) {
                            $telegramUpdateData['name'] = $validated['name'];
                        }
                        
                        if (!empty($telegramUpdateData)) {
                            $telegramUser->update($telegramUpdateData);
                        }
                    }
                }
            }

            // Location processing moved to SendMsgToAdmin command for better performance

                    // Создаем мастера только если его еще нет
            if (!$existingMaster) {
                $master = Master::create([
                    'text' => $validated['text'],
                    'location_comm' => $validated['location_comm'] ?? null,
                    'telegram_entities' => isset($validated['entities']) ? json_decode($validated['entities'], true) : null,
                    'uid' => $uid,
                    'source_status' => \App\Models\Master::SOURCE_STATUS_API_NEW,
                ]);
            }

            // Locations will be processed later in SendMsgToAdmin command

            // Process uploaded files if provided
            $files = $request->allFiles();
            $fileStats = [
                'total_received' => count($files),
                'processed' => 0,
                'added_new' => 0,
                'skipped_duplicates' => 0,
                'invalid_files' => 0,
                'errors' => []
            ];
            
            foreach ($files as $key => $file) {
                if (str_starts_with($key, 'file_')) {
                    $fileStats['processed']++;
                    
                    try {
                        $filename = $file->getClientOriginalName();
                        $mimeType = $file->getMimeType();
                        $size = $file->getSize();
                        
                        // Generate file hash to prevent duplicates
                        $fileHash = hash_file('sha256', $file->getPathname());
                        
                        // Check if file with same hash already exists
                        $existingFile = MasterMedia::where('file_hash', $fileHash)->first();
                        if ($existingFile) {
                            $fileStats['skipped_duplicates']++;
                            continue;
                        }
                        
                        // Save file
                        $path = $file->store('masters/' . $master->id);
                        
                        // Determine file type from MIME type
                        $fileType = 'document'; // default
                        if (str_starts_with($mimeType, 'image/')) {
                            $fileType = 'photo';
                        } elseif (str_starts_with($mimeType, 'video/')) {
                            $fileType = 'video';
                        } elseif (str_starts_with($mimeType, 'audio/')) {
                            $fileType = 'audio';
                        }
                        
                        // Create media record with hash
                        MasterMedia::create([
                            'master_id' => $master->id,
                            'file_path' => $path,
                            'original_name' => $filename,
                            'file_type' => $fileType,
                            'file_size' => $size,
                            'file_hash' => $fileHash,
                            'mime_type' => $mimeType,
                        ]);
                        
                        $fileStats['added_new']++;
                        
                    } catch (\Exception $e) {
                        $fileStats['errors'][] = [
                            'file' => $filename ?? $key,
                            'error' => $e->getMessage()
                        ];
                    }
                } else {
                    $fileStats['invalid_files']++;
                }
            }

            $response = [
                'status' => $existingMaster ? 'updated' : 'created', 
                'uid' => $uid,
                'mid' => $master->id,
                'media_count' => $fileStats['added_new'],
                'file_stats' => $fileStats
            ];

            return response()->json($response);
        
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'message' => $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('MasterController store error: ' . $e->getMessage() . ' | File: ' . $e->getFile() . ' | Line: ' . $e->getLine());
            
            return response()->json([
                'error' => 'Internal server error',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }
    
}
