<?php
/**
 * BGA view class for HackNSlash.
 */

require_once(APP_BASE_PATH . 'view/common/game.view.php');

class view_hacknslash_hacknslash extends game_view
{
    public function getGameName()
    {
        return 'hacknslash';
    }

    public function build_page($viewArgs)
    {
        $this->tpl['BOARD_TITLE'] = self::_('Dungeon');
        $this->tpl['BOARD_HINT'] = self::_('Select a card, then choose a target on the board.');
        $this->tpl['MONSTERS_TITLE'] = self::_('Monsters');
        $this->tpl['EVENTS_TITLE'] = self::_('Events');
        $this->tpl['EVENTS_EMPTY'] = self::_('Events will appear here.');
        $this->tpl['HAND_TITLE'] = self::_('Hero cards');
        $this->tpl['STATUS_TITLE'] = self::_('Active hero');
        $this->tpl['PARTNER_TITLE'] = self::_('Partner');
    }
}
