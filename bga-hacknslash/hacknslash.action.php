<?php
/**
 * Player action entry points for BGA Ajax calls.
 */

class action_hacknslash extends APP_GameAction
{
    public function __default()
    {
        if ($this->isArg('notifwindow')) {
            $this->view = 'common_notifwindow';
            $this->viewArgs['table'] = $this->getArg('table', AT_posint, true);
        } else {
            $this->view = 'hacknslash_hacknslash';
        }
    }

    public function actMove()
    {
        self::setAjaxMode();
        $tileId = self::getArg('tile_id', AT_posint, true);
        $this->game->actMove($tileId);
        self::ajaxResponse();
    }

    public function actPlayCard()
    {
        self::setAjaxMode();
        $cardId = self::getArg('card_id', AT_posint, true);
        $payload = self::resolvePowerPayload();
        $this->game->actPlayCard($cardId, $payload);
        self::ajaxResponse();
    }

    public function actAttack()
    {
        self::setAjaxMode();
        $targetId = self::getArg('target_id', AT_posint, true);
        $this->game->actAttack($targetId);
        self::ajaxResponse();
    }

    public function actEndTurn()
    {
        self::setAjaxMode();
        $this->game->actEndTurn();
        self::ajaxResponse();
    }

    /**
     * Build the optional payload that drives a power resolution.
     *
     * BGA's `getArg` does not natively support arrays, so we accept a
     * comma-separated list for `target_entity_ids`. Each scalar id stays a
     * positive integer; missing fields default to zero so the resolver can
     * apply its own defaults.
     *
     * @return array{target_entity_id:int, target_tile_id:int, selected_tile_id:int, target_entity_ids: list<int>}
     */
    private function resolvePowerPayload(): array
    {
        $targetEntityId = (int) self::getArg('target_entity_id', AT_posint, false, 0);
        $targetTileId = (int) self::getArg('target_tile_id', AT_posint, false, 0);
        $selectedTileId = (int) self::getArg('selected_tile_id', AT_posint, false, 0);
        $rawIds = (string) self::getArg('target_entity_ids', AT_alphanum, false, '');

        $targetEntityIds = [];
        if ($rawIds !== '') {
            foreach (explode(',', $rawIds) as $value) {
                $value = trim($value);
                if ($value === '' || !ctype_digit($value)) {
                    continue;
                }
                $targetEntityIds[] = (int) $value;
            }
        }

        return [
            'target_entity_id' => $targetEntityId,
            'target_tile_id' => $targetTileId,
            'selected_tile_id' => $selectedTileId,
            'target_entity_ids' => $targetEntityIds,
        ];
    }
}
