<?php
/**
 *
 * phpBB lockers. An extension for the phpBB Forum Software package.
 *
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace jeb\locker\event;

/**
 * @ignore
 */
use phpbb\auth\auth;
use phpbb\config\config;
use phpbb\controller\helper;
use phpbb\db\driver\driver;
use phpbb\db\driver\driver_interface;
use phpbb\notification\manager;
use phpbb\template\template;
use phpbb\user;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * phpBB lockers Event listener.
 */
class main_listener implements EventSubscriberInterface
{
	/**{{{
	 * @var string
	 */
	private $regex = '#\[locker\]<\/s>(.*?)<e>\[\/locker\]#';

	/**
	 * @var helper
	 */
	protected $helper;

	/**
	 * @var template
	 */
	protected $template;

	/**
	 * @var driver
	 */
	private $db;

	/**
	 * @var manager
	 */
	private $notification_manager;

	/**
	 * @var user
	 */
	private $user;

	/**
	 * @var array
	 */
	private $locker_data;

	/**
	 * @var auth
	 */
	private $auth;
	/**
	 * @var config
	 */
	private $config;
	/**
	 * @var string
	 */
	private $php_ext;

	/**
	 * Constructor
	 *
	 * @param helper $helper Controller helper object
	 * @param template $template Template object
	 * @param driver_interface $db
	 * @param manager $notification_manager
	 * @param user $user
	 * @param auth $auth
	 * @param config $config
	 * @param string $php_ext
	 * @internal param viewonline_helper $viewonline_helper
	 *//*}}}*/

    public function __construct(helper $helper, template $template, driver_interface $db, manager $notification_manager, user $user, auth $auth, config $config, $php_ext,
        $sauth
    )/*{{{*/
	{
		$this->helper = $helper;
		$this->template = $template;
		$this->db = $db;
		$this->notification_manager = $notification_manager;
		$this->user = $user;
		$this->auth = $auth;
		$this->config = $config;
		$this->php_ext = $php_ext;
		$this->sauth = $sauth;
	}/*}}}*/

	static public function getSubscribedEvents()/*{{{*/
	{
        return [
            'core.submit_post_end'                 => 'do_create_locker',
            'core.user_setup'                      => 'setup_user',
            'core.text_formatter_s9e_parse_before' => [
                ['modify_lock', 0],
            ],
            'core.posting_modify_template_vars'    => 'remove_locker_in_quote',
            // 'core.viewtopic_modify_page_title'     => 'viewtopic',
        ];
    }/*}}}*/

    public function setup_user($event)/*{{{*/
    {
        $this->user_id = $this->user->data['user_id'];
        $this->lock_data = [];
    }/*}}}*/

    public function do_create_locker($event)/*{{{*/
    {
        $data = $event['data'];
        $post_id = $data['post_id'];
        $poster_id = $data['poster_id'];
        $event['data'] = $data;
        foreach($this->lock_data as $data)
        {
            $this->create_locker($poster_id, $post_id, $data['hash'], $data['text']);
        }
    }/*}}}*/

    private function create_locker($user_id, $post_id, $hash, $text)/*{{{*/
    {
        global $table_prefix;
        $data = [
            'user_id' => $user_id,
            'post_id' => $post_id,
            'hash' => $hash,
            'text' => $text,
        ];
        $sql = 'INSERT INTO ' . $table_prefix . 'locker' . $this->db->sql_build_array('INSERT', $data) .
            " ON DUPLICATE KEY UPDATE " . $this->db->sql_build_array('UPDATE', $data);
        $this->db->sql_query($sql);
    }/*}}}*/

    private function replace_lock_with_lockbox($strn)/*{{{*/
    {
        $permission = false;
        if ($this->sauth->user_belongs_to_groupset($this->user_id, 'Blue Team'))
        {
            $permission = true;
        }
        $strn = strip_tags($strn);
        $strn = iconv("UTF-8", "ISO-8859-1//IGNORE", $strn);
        $ptn = '#\[lock\](.*?)\[\/lock\]#uis';
        $strn = preg_replace_callback($ptn, function($matches) use($permission) {
            // $content = md5($matches[1]);
            if ($permission)
            {
                $content = hash('sha1', $matches[1]);
                $this->lock_data[] = [
                    'hash' => $content,
                    'text' => $matches[1],
                ];
                return '[locker]' . $content . '[/locker]';
            }
            return ' *** You cannot use snahp lockers ***';
        }, $strn);
        return $strn;
    }/*}}}*/

    public function modify_lock($event)/*{{{*/
    {
        $text = $event['text'];
        $text = $this->replace_lock_with_lockbox($text);
        $event['text'] = $text;
    }/*}}}*/

    public function add_permission($event)/*{{{*/
    {
        $permissions = $event['permissions'];
        $permissions['u_can_locker'] = array('lang' => 'ACL_U_CAN_LOCKER', 'cat' => 'misc');
        $event['permissions'] = $permissions;
    }/*}}}*/

    /**
     * @param array $event
     */
    public function viewtopic($event)/*{{{*/
    {
        $s_quick_reply = false;
        if ($this->user->data['is_registered'] && $this->config['allow_quick_reply'] && ($event['topic_data']['forum_flags'] & FORUM_FLAG_QUICK_REPLY) && $this->auth->acl_get('f_reply', $event['forum_id']))
        {
            // Quick reply enabled forum
            $s_quick_reply = (($event['topic_data']['forum_status'] == ITEM_UNLOCKED && $event['topic_data']['topic_status'] == ITEM_UNLOCKED) || $this->auth->acl_get('m_edit', $event['forum_id'])) ? true : false;
        }
        if ($s_quick_reply)
        {
            $this->template->assign_vars([
                'UA_AJAX_LOCKER_URL'    => $this->helper->route('jeb_locker_controller'),
            ]);
        }
    }/*}}}*/

    /**
     * Remove locker BBCode from quote.
     * @param array $event
     */
    public function remove_locker_in_quote($event)/*{{{*/
    {
        if ($event['submit'] || $event['preview'] || $event['refresh'] || $event['mode'] != 'quote' || !isset($event['page_data']) || !isset($event['page_data']['MESSAGE']))
        {
            return;
        }
        $page_data = $event['page_data'];
        $page_data['MESSAGE'] = preg_replace('#\[locker\](.*?)\[\/locker\]#uis', '', $page_data['MESSAGE']);
        $event['page_data'] = $page_data;
    }/*}}}*/

}
