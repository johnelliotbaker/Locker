<?php
namespace jeb\locker\core\auth;

class user_auth
{
    protected $db;
    protected $auth;
    protected $user;
    protected $container;
    protected $this_user_id;
	public function __construct(
        $db, $user, $auth, $container
	)
	{
        $this->db = $db;
        $this->user = $user;
        $this->auth = $auth;
        $this->container = $container;
        $this->this_user_id = $this->user->data['user_id'];
	}

    public function is_dev_server()/*{{{*/
    {
        global $config;
        $servername = $config['server_name'];
        return isset($servername) && $servername=='192.168.2.12';
    }/*}}}*/

    public function reject_group($column, $group_id)/*{{{*/
    {
        $sql = 'SELECT COUNT(group_id) as count from ' . GROUPS_TABLE . " WHERE {$column}=1 AND group_id={$group_id}";
        $result = $this->db->sql_query($sql);
        $row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);
        $b = $row && $row['count'] ? true : false;
        if (!$b)
        {
            trigger_error('Permission Error. Error Code: ca546fad27');
        }
        return true;
    }/*}}}*/

    public function reject_bots()/*{{{*/
    {
        // $BOT_GID = 6
        $BOTS_GID = 6;
        $gid = $this->user->data['group_id'];
        if (!$gid || $gid == $BOTS_GID)
            trigger_error('Access to bots has been denied.');
    }/*}}}*/

    public function reject_anon()/*{{{*/
    {
        $uid = $this->user->data['user_id'];
        if ($uid == ANONYMOUS)
            trigger_error('You must login before venturing forth.');
    }/*}}}*/

    public function is_admin()/*{{{*/
    {
        return $this->auth->acl_gets('a_');
    }/*}}}*/

    public function is_only_dev()/*{{{*/
    {
        include_once('includes/functions_user.php');
        // TODO Get better method for checking for developer roles
        $gid_developer = 13;
        $uid_developer = 10414;
        $user_id = $this->user->data['user_id'];
        $b_dev = group_memberships($gid_developer, $user_id, true) && $user_id==$uid_developer;
        return $b_dev;
    }/*}}}*/

    public function is_dev()/*{{{*/
    {
        include_once('includes/functions_user.php');
        // TODO Get better method for checking for developer roles
        $gid_developer = 13;
        $uid_developer = 10414;
        $user_id = $this->user->data['user_id'];
        $b_dev = group_memberships($gid_developer, $user_id, true) && $user_id==$uid_developer;
        return $this->auth->acl_gets('a_', 'm_') || $b_dev;
    }/*}}}*/

    public function is_mod()/*{{{*/
    {
        return $this->auth->acl_gets('a_', 'm_');
    }/*}}}*/

    public function is_self($user_id)/*{{{*/
    {
        return $user_id==$this->user->data['user_id'];
    }/*}}}*/

    public function is_op($topic_data)/*{{{*/
    {
        $poster_id = $topic_data['topic_poster'];
        $user_id = $this->user->data['user_id'];
        return $poster_id == $user_id;
    }/*}}}*/

    public function reject_non_dev_or_self($user_id, $append='')/*{{{*/
    {
        if (!($this->is_dev() || $this->is_self($user_id)))
            trigger_error('You don\'t have the permission to access this page. Error Code: 7f105fe648 ' . $append);
    }/*}}}*/

    public function reject_non_dev_or_op($append='')/*{{{*/
    {
        if (!($this->is_dev() || $this->is_self()))
            trigger_error('You don\'t have the permission to access this page. Error Code: 7f105fe648 ' . $append);
    }/*}}}*/

    public function reject_non_dev($append='')/*{{{*/
    {
        if (!$this->is_dev())
            trigger_error('You don\'t have the permission to access this page. Error Code: d198252910 ' . $append);
    }/*}}}*/

    public function reject_non_admin($append='')/*{{{*/
    {
        if (!$this->is_admin())
            trigger_error('Only administrator may access this page. ' . $append);
    }/*}}}*/

    public function reject_non_moderator($append='')/*{{{*/
    {
        if (!$this->is_mod())
            trigger_error('Only moderators may access this page. ' . $append);
    }/*}}}*/

    public function reject_non_group($group_id, $perm_name)/*{{{*/
    {
        $sql = 'SELECT 1 FROM ' . GROUPS_TABLE . '
            WHERE group_id=' . $group_id . ' AND 
            ' . $perm_name . '=1';
        $result = $this->db->sql_query($sql, 1);
        $row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);
        if (!$row)
            trigger_error('Your don\'t have the permission to access this page.');
    }/*}}}*/

    public function reject_user_not_in_groupset($user_id, $groupset_name)/*{{{*/
    {
        if (!$this->user_belongs_to_groupset($user_id, $groupset_name))
        {
            trigger_error('You do not have the permission to view this page. Error Code: ad5611c89b');
        }
    }/*}}}*/

    public function user_belongs_to_groupset($user_id, $groupset_name)/*{{{*/
    {
        if ($this->is_dev_server())
        {
            $groupset = $this->container->getParameter('jeb.locker.groups')['dev']['set'];
        }
        else
        {
            $groupset = $this->container->getParameter('jeb.locker.groups')['production']['set'];
        }
        include_once('includes/functions_user.php');
        $user_id_ary = [$user_id];
        if (!array_key_exists($groupset_name, $groupset))
        {
            return false;
        }
        $group_id_ary = $groupset[$groupset_name];
        $res = group_memberships($group_id_ary, $user_id_ary);
        return !!$res;
    }/*}}}*/

    public function user_belongs_to_group($user_id, $group_id)/*{{{*/
    {
        include_once('includes/functions_user.php');
        $user_id_ary = [$user_id];
        $group_id_ary = [$group_id];
        $res = group_memberships($group_id_ary, $user_id_ary);
        return !!$res;
    }/*}}}*/

}
