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
            'core.user_setup' => 'setup_user',
            'core.submit_post_end' => 'do_create_locker',
            'core.viewtopic_modify_page_title' => 'viewtopic',
            'core.posting_modify_template_vars' => 'remove_locker_in_quote',
            'core.posting_modify_submit_post_before' => 'modify_lock',
        ];
    }/*}}}*/

    public function modify_lock($event)/*{{{*/
    {
        if (!$this->sauth->user_belongs_to_groupset($this->user_id, 'Blue Team'))
        {
            return false;
        }
        $flags = 0;
        $uid = $bitfield = $options = '';
        $post_data = $event['post_data'];
        $enable_bbcode = $post_data['enable_bbcode'];
        $enable_urls = $post_data['enable_urls'];
        $enable_smilies = $post_data['enable_smilies'];
        $data = $event['data'];
        $message = $data['message'];
        $message = generate_text_for_edit($message, $uid, $flags)['text'];
        $message = $this->replace_lock_with_lockbox($message);
        generate_text_for_storage($message, $uid, $bitfield, $options, $enable_bbcode, $enable_urls, $enable_smilies);
        $data['message'] = $message;
        $event['data'] = $data;
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
        $ptn = '#\[lock\](.*?)\[\/lock\]#uis';
        $strn = preg_replace_callback($ptn, function($matches) {
            $text = $matches[1];
            if (strlen($text) >= 250)
            {
                return 'Locker content must be less than 250 characters.' . PHP_EOL . $matches[0];
            }
            $hash = hash('sha1', $text);
            $this->lock_data[] = [
                'hash' => $hash,
                'text' => $text,
            ];
            return '[locker]' . $hash . '[/locker]';
        }, $strn);
        return $strn;
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
        $topic_data = $event['topic_data'];
        $b_owner = $this->sauth->is_op($topic_data) || $this->sauth->is_dev();
        $this->template->assign_vars([
            'B_LOCKER_OWNER' => $b_owner,
        ]);
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
