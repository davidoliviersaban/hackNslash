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
        $this->game->actPlayCard($cardId);
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
}
