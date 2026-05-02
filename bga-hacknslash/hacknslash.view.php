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
        $this->tpl['HAND_TITLE'] = self::_('Hand');
        $this->tpl['STATUS_TITLE'] = self::_('Status');
    }
}
