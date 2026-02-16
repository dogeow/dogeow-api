<?php

namespace App\Http\Controllers\Api\Game;

use App\Http\Controllers\Controller;
use App\Http\Requests\Game\UpdatePotionSettingsRequest;
use App\Http\Requests\Game\UsePotionRequest;
use App\Http\Requests\StartCombatRequest;
use App\Services\Game\GameCombatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class CombatController extends Controller
{
    use \App\Http\Controllers\Concerns\CharacterConcern;

    public function __construct(
        private readonly GameCombatService $combatService,
    ) {}

    /**
     * 获取战斗状态
     */
    public function status(Request $request): JsonResponse
    {
        try {
            $character = $this->getCharacter($request);
            $result = $this->combatService->getCombatStatus($character);

            return $this->success($result);
        } catch (Throwable $e) {
            return $this->error('获取战斗状态失败', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 开始挂机战斗
     */
    public function start(StartCombatRequest $request): JsonResponse
    {
        try {
            $character = $this->getCharacter($request);
            $result = $this->combatService->startCombat($character);

            return $this->success($result);
        } catch (Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 停止挂机战斗
     */
    public function stop(Request $request): JsonResponse
    {
        try {
            $character = $this->getCharacter($request);
            $result = $this->combatService->stopCombat($character);

            return $this->success($result);
        } catch (Throwable $e) {
            return $this->error('停止战斗失败', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 更新药水自动使用设置
     */
    public function updatePotionSettings(UpdatePotionSettingsRequest $request): JsonResponse
    {
        try {
            $character = $this->getCharacter($request);
            $character = $this->combatService->updatePotionSettings($character, $request->validated());

            return $this->success(['character' => $character->toArray()], '药水设置已更新');
        } catch (Throwable $e) {
            return $this->error('更新药水自动使用设置失败', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 执行一回合战斗
     */
    public function execute(Request $request): JsonResponse
    {
        try {
            $character = $this->getCharacter($request);

            $skillIds = $request->input('skill_ids') ?? [];
            if (! is_array($skillIds) && $request->has('skill_id')) {
                $skillIds = [(int) $request->input('skill_id')];
            }

            $result = $this->combatService->executeRound($character, $skillIds);

            return $this->success($result);
        } catch (Throwable $e) {
            // 处理特殊异常 - 自动停止战斗
            if ($e->getPrevious() && str_contains($e->getMessage(), 'auto_stopped')) {
                return $this->error($e->getMessage(), json_decode($e->getPrevious()->getMessage(), true) ?: []);
            }

            return $this->error($e->getMessage() ?: '战斗执行失败', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 获取战斗日志
     */
    public function logs(Request $request): JsonResponse
    {
        try {
            $character = $this->getCharacter($request);
            $result = $this->combatService->getCombatLogs($character);

            return $this->success($result);
        } catch (Throwable $e) {
            return $this->error('获取战斗日志失败', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 获取战斗统计
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $character = $this->getCharacter($request);
            $result = $this->combatService->getCombatStats($character);

            return $this->success($result);
        } catch (Throwable $e) {
            return $this->error('获取战斗统计失败', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 使用药品
     */
    public function usePotion(UsePotionRequest $request): JsonResponse
    {
        try {
            $character = $this->getCharacter($request);
            $inventoryService = new \App\Services\Game\GameInventoryService;
            $result = $inventoryService->usePotion($character, $request->input('item_id'));

            return $this->success([
                'current_hp' => $character->getCurrentHp(),
                'current_mana' => $character->getCurrentMana(),
                'max_hp' => $character->getMaxHp(),
                'max_mana' => $character->getMaxMana(),
                'message' => $result['message'] ?? '药品使用成功',
            ], '药品使用成功');
        } catch (Throwable $e) {
            return $this->error('使用药品失败', ['error' => $e->getMessage()]);
        }
    }
}
