<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Location;
use Idpromogroup\LaravelOpenAIAssistants\Facades\OpenAIAssistants;

/**
 * API для создания заказов
 * 
 * Аутентификация: X-Api-Secret заголовок
 * Базовый URL: /api/v1/orders
 * 
 * Примеры запросов:
 * 
 * 1. Создать external заказ (по умолчанию):
 * curl -X POST https://fixfox.app/api/v1/orders \
 *   -H "X-Api-Secret: Ap1_K9f!xQmZ3gR7vL2w*D8hNeYsBc4Xt9UJMa0pVEs" \
 *   -H "Content-Type: application/json" \
 *   -d '{
 *     "text": "Заказ с форума: нужен мастер для ремонта. Ссылка: https://forum.example.com/topic/123"
 *   }'
 * 
 * 2. Создать internal заказ:
 * curl -X POST http://your-domain/api/v1/orders \
 *   -H "X-Api-Secret: Ap1_K9f!xQmZ3gR7vL2w*D8hNeYsBc4Xt9UJMa0pVEs" \
 *   -H "Content-Type: application/json" \
 *   -d '{
 *     "text": "Заказ через API",
 *     "order_type": "internal",
 *     "uid": 123
 *   }'
 * 
 * Поля:
 * - text (обязательное): текст заказа
 * - order_type (опциональное): "internal" или "external" (по умолчанию "external")
 * - uid (опциональное): ID пользователя (по умолчанию 1 для external заказов)
 * 
 * Ответ:
 * {
 *   "status": "ok",
 *   "id": 123
 * }
 */

class OrderController extends Controller
{
    public function index()
    {
        return response()->json(Order::latest()->get());
    }

    public function store(Request $request)
    {
        // Отключаем debug вывод для этой сессии
        config(['openai-assistants.debug_output' => false]);
        
        $validated = $request->validate([
            'text' => 'required|string',
            'lot_name' => 'nullable|string',
            'uid' => 'nullable|integer',
            'order_type' => 'nullable|in:internal,external',
        ]);

        // Location processing moved to SendMsgToAdmin command for faster API response time
        // AI location extraction takes 2-3 seconds - moved to background processing

        $order = Order::create([
            'text' => $validated['text'],
            'lot_name' => $validated['lot_name'] ?? null,
            'uid' => $validated['uid'] ?? 1, // для external заказов uid=1
            'order_type' => $validated['order_type'] ?? Order::TYPE_EXTERNAL,
        ]);

        // Locations will be extracted and attached by SendMsgToAdmin background command

        return response()->json(['status' => 'ok', 'id' => $order->id]);
    }

    public function show($id)
    {
        $order = Order::findOrFail($id);
        return response()->json($order);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'text' => 'sometimes|required|string',
            'lot_name' => 'sometimes|nullable|string',
            'uid' => 'sometimes|nullable|integer',
            'order_type' => 'sometimes|nullable|in:internal,external',
        ]);

        $order = Order::findOrFail($id);
        $order->update($validated);

        return response()->json(['status' => 'updated', 'order' => $order]);
    }

    public function destroy($id)
    {
        $order = Order::findOrFail($id);
        $order->delete();

        return response()->json(['status' => 'deleted']);
    }
}
