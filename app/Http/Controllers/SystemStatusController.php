<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use App\Models\LogEntry;
use App\Models\Order;
use App\Models\Master;
use App\Models\TelegramUser;
use App\Models\MasterOrderCheck;
use App\Models\TelegramLog;
use Illuminate\Support\Carbon;

class SystemStatusController extends Controller
{
    public function index()
    {
        $report = [];

        // 1. Статистика заказов
        $totalOrders = Order::count();
        $activeOrders = Order::where('status', Order::STATUS_IN_WORK)->count();
        
        // 2. Статистика мастеров
        $totalMasters = Master::count();
        $activeMasters = Master::where('status', Master::STATUS_IN_WORK)->count();
        
        // 3. Статистика пользователей Telegram
        $totalTelegramUsers = TelegramUser::count();
        $activeTelegramUsers = TelegramUser::where('activity_status', TelegramUser::ACTIVITY_STATUS_ACTIVE)->count();
        
        $report[] = [
            'module' => 'Data',
            'status' => 'OK',
            'level' => 'info',
            'sub' => '',
            'msg' => "Orders active: $activeOrders, total: $totalOrders, Masters active: $activeMasters, total $totalMasters; Telegram users active: $activeTelegramUsers, total: $totalTelegramUsers",
        ];

        // 4. Проверка выполнения FindMastersForOrders
        $problematicOrderIds = DB::table('orders as o')
            ->leftJoin('master_order_checks as moc', 'o.id', '=', 'moc.order_id')
            ->where('o.status', Order::STATUS_IN_WORK)
            ->where('o.updated_at', '<', now()->subMinutes(10))
            ->where(function($query) {
                $query->whereNull('moc.order_id')  // Нет записи вообще
                      ->orWhereNull('moc.checked_at')  // Первая проверка не сделана
                      ->orWhereNull('moc.checked_2_at'); // Вторая проверка не сделана
            })
            ->limit(5)
            ->pluck('o.id')
            ->toArray();

        if (!empty($problematicOrderIds)) {
            $orderIdsList = implode(', ', $problematicOrderIds);
            $report[] = [
                'module' => 'FindMastersForOrders',
                'status' => 'Critical',
                'level' => 'crit',
                'sub' => '',
                'msg' => "Problematic orders (>10 min without completed checks): #{$orderIdsList}",
            ];
        }

        // 5. Ошибки логов за последние 12 часов
        $errors = LogEntry::where('created_at', '>=', now()->subHours(12))
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        if ($errors->isNotEmpty()) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->created_at->format('Y-m-d H:i:s') . ' - ' . strip_tags(preg_replace('/\s+/', ' ', $error->message));
            }

            $report[] = [
                'module' => 'LogEntries',
                'status' => 'Error',
                'level' => 'crit',
                'sub' => '',
                'msg' => implode("\n", $errorMessages),
            ];
        }

        // 6. Ошибки Telegram за последние 24 часа
        $telegramErrors = TelegramLog::whereNotNull('error')
            ->where('error', '!=', '{"ok":false,"error_code":400,"description":"Bad Request: chat not found"}')
            ->where('error', '!=', '{"ok":false,"error_code":400,"description":"Bad Request: message to delete not found"}')
            ->where('error', '!=', '{"ok":false,"error_code":403,"description":"Forbidden: bot was blocked by the user"}')
            ->where('created_at', '>=', now()->subHours(24))
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        if ($telegramErrors->isNotEmpty()) {
            $telegramErrorMessages = [];
            foreach ($telegramErrors as $error) {
                $telegramErrorMessages[] = $error->created_at->format('Y-m-d H:i:s') . ' - ' . strip_tags(preg_replace('/\s+/', ' ', $error->error));
            }

            $report[] = [
                'module' => 'TelegramErrors',
                'status' => 'Error',
                'level' => 'crit',
                'sub' => '',
                'msg' => implode("\n", $telegramErrorMessages),
            ];
        }


        // 7. Server Load - показываем только при проблемах
        $load = sys_getloadavg();
        $cpuCores = 1; // Можно получить через sys_getloadavg() или другой способ
        $loadThreshold = $cpuCores * 0.8; // Порог 80% от количества ядер
        
        if ($load[0] > $loadThreshold || $load[1] > $loadThreshold || $load[2] > $loadThreshold) {
            $report[] = [
                'module' => 'ServerLoad',
                'status' => 'Warning',
                'level' => 'warn',
                'sub' => '',
                'msg' => 'High server load (1, 5, 15 min): ' . implode(', ', $load),
            ];
        }

        // 8. Disk space - показываем только при проблемах
        $diskFree = disk_free_space("/");
        $diskTotal = disk_total_space("/");
        $diskUsagePercent = (($diskTotal - $diskFree) / $diskTotal) * 100;
        
        if ($diskUsagePercent > 80) { // Показываем если занято больше 80%
            $report[] = [
                'module' => 'DiskSpace',
                'status' => 'Warning',
                'level' => 'warn',
                'sub' => '',
                'msg' => 'Low disk space: ' . round($diskFree / 1024 / 1024 / 1024, 1) . ' GB free of ' . round($diskTotal / 1024 / 1024 / 1024, 1) . ' GB (' . round($diskUsagePercent, 1) . '% used)',
            ];
        }

        return response()->json($report, 200, [], JSON_PRETTY_PRINT);
    }
}
