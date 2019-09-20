<?php
/**
 *
 * phpBB lockers. An extension for the phpBB Forum Software package.
 *
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace jeb\locker\controller;

use phpbb\auth\auth;
use phpbb\config\config;
use phpbb\db\driver\driver_interface;
use phpbb\exception\http_exception;
use phpbb\request\request_interface;
use phpbb\user;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * phpBB lockers main controller.
 */
class main
{
	/**
	 * @var user
	 */
	protected $user;

	/**
	 * @var driver_interface
	 */
	private $db;

	/**
	 * @var auth
	 */
	private $auth;

	/**
	 * @var request_interface
	 */
	private $request;

	/**
	 * @var config
	 */
	private $config;

	/**
	 * Constructor
	 *
	 * @param user $user
	 * @param driver_interface $db
	 * @param auth $auth
	 * @param request_interface $request
	 * @param config $config
	 */
    public function __construct(user $user, driver_interface $db, auth $auth, request_interface $request, config $config, $template, $helper,
        $tbl_locker, $tbl_locker_usage,
        $sauth
    )
	{
		$this->user = $user;
		$this->db = $db;
		$this->auth = $auth;
		$this->request = $request;
		$this->config = $config;
		$this->template = $template;
		$this->helper = $helper;
        $this->tbl_locker = $tbl_locker;
        $this->tbl_locker_usage = $tbl_locker_usage;
        $this->sauth = $sauth;
        $this->user_id = (int) $this->user->data['user_id'];
        $this->sql_cd = 0;
	}

    private function respond_locker_content_as_json($hash)/*{{{*/
    {
        $sql = 'SELECT * FROM ' . $this->tbl_locker . " WHERE hash='${hash}'";
        $result = $this->db->sql_query($sql);
        $row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);
        if (!$row)
        {
            return new JsonResponse(['status' => 0]);
        }
        $b_success = $this->log_usage($hash);
        if ($b_success)
        {
            $text = htmlspecialchars($row['text']);
            return new JsonResponse(['status' => 1, 'text' => $text]);
        }
        return new JsonResponse(['status' => 0]);
    }/*}}}*/

    private function select_locker_with_hash($hash)/*{{{*/
    {
        $where = "a.hash='${hash}'";
        $sql_array = [
            'SELECT'	=> '*',
            'FROM'		=> [ $this->tbl_locker => 'a', ],
            'WHERE'		=> $where,
        ];
        $sql = $this->db->sql_build_query('SELECT', $sql_array);
        $result = $this->db->sql_query($sql, $this->sql_cd);
        $row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);
        return $row;
    }/*}}}*/

    private function select_locker_usage_with_locker_id($locker_id, $user_id=null)/*{{{*/
    {
        if ($user_id===null)
        {
            $user_id = $this->user_id;
        }
        $where = "a.locker_id=${locker_id} AND a.user_id=${user_id}";
        $sql_array = [
            'SELECT'	=> '*',
            'FROM'		=> [ $this->tbl_locker_usage => 'a', ],
            'WHERE'		=> $where,
        ];
        $sql = $this->db->sql_build_query('SELECT', $sql_array);
        $result = $this->db->sql_query($sql, $this->sql_cd);
        $row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);
        return $row;
    }/*}}}*/

    private function log_usage($hash)/*{{{*/
    {
        $hash = $this->db->sql_escape($hash);
        $locker_data = $this->select_locker_with_hash($hash);
        if (!$locker_data)
        {
            return false;
        }
        $locker_id = (int) $locker_data['id'];
        $user_id = $this->user_id;
        $locker_usage_data = $this->select_locker_usage_with_locker_id($locker_id, $user_id);
        $time = time();
        if ($locker_usage_data)
        {
            $sql = 'UPDATE ' . $this->tbl_locker_usage . ' SET ' . 
                " n_access=n_access+1, latest_access_time=${time}". 
                " WHERE locker_id=${locker_id} AND user_id=${user_id}";
            $this->db->sql_query($sql);
            return true;
        }
        else
        {
            $data = [
                'locker_id' => $locker_id,
                'user_id' => $user_id,
                'first_access_time' => $time,
                'latest_access_time' => $time,
                'n_access' => 1,
            ];
            $sql = 'INSERT INTO ' . $this->tbl_locker_usage . $this->db->sql_build_array('INSERT', $data);
            $this->db->sql_query($sql);
            return true;
        }
    }/*}}}*/

	/**
	 *
	 * @return \Symfony\Component\HttpFoundation\Response A Symfony Response object
	 */
	public function handle($mode)/*{{{*/
	{
		if ($this->user->data['user_id'] == ANONYMOUS || $this->user->data['is_bot'])
		{
			throw new http_exception(401);
		}
        switch($mode)
        {
        case 'unlock':
            $post_id = $this->request->variable('p', 0);
            $hash = $this->db->sql_escape($this->request->variable('h', ''));
            if (!$hash)
            {
                return new JsonResponse(['status' => 0]);
            }
            return $this->respond_locker_content_as_json($hash);
        case 'common_thanks':
            // $hash = $this->db->sql_escape($this->request->variable('h', ''));
            $hash = $this->db->sql_escape($this->request->variable('common_thanks_hashes', ''));
            // if (!$hash)
            // {
            //     return new JsonResponse(['status' => 0]);
            // }
            return $this->respond_common_thanks($hash);
        }
	}/*}}}*/

    private function filter_valid_hash($a_hash)/*{{{*/
    {
        $res = [];
        foreach($a_hash as $hash)
        {
            if (ctype_xdigit($hash))
            {
                $res[] = $hash;
            }
        }
        return $res;
    }/*}}}*/

    private function respond_common_thanks_as_json($hash_string)/*{{{*/
    {
        $rowset = $this->get_common_thanks($hash_string);
        $rowset = $this->process_common_thanks($rowset);
        if (!$rowset)
        {
            return new JsonResponse(['status' => 0]);
        }
        // return new JsonResponse([]);
        return new JsonResponse(['status' => 1, 'data' => $rowset]);
    }/*}}}*/

    private function process_common_thanks($rowset)/*{{{*/
    {
        $res = [];
        foreach($rowset as $row)
        {
            $user_id = $row['user_id'];
            if (!array_key_exists($user_id, $res))
            {
                $res[$user_id] = [];
                $res[$user_id]['username'] = $row['username'];
                $res[$user_id]['user_id'] = $row['user_id'];
                $res[$user_id]['count'] = 0;
                $res[$user_id]['n_access'] = 0;
                $res[$user_id]['data'] = [];
            }
            $res[$user_id]['count'] += 1;
            $res[$user_id]['n_access'] += $row['n_access'];
            $res[$user_id]['data'][] = $row['hash'];
        }
        usort($res, function($a, $b){
            if ($a['count'] == $b['count'])
            {
                return $a['username'] > $b['username'];
            }
            return $a['count'] < $b['count'];
        });
        return $res;
    }/*}}}*/

    private function get_common_thanks($hash_string)/*{{{*/
    {
        $a_hash = explode(',', $hash_string);
        $a_hash = array_map('trim', $a_hash);
        $a_hash = $this->filter_valid_hash($a_hash);
        if (!$a_hash) { return []; };
        $a_hash = array_unique($a_hash);
        $hash_in_set = $this->db->sql_in_set('b.hash', $a_hash);
        $where = $hash_in_set;
        $sql_array = [
            'SELECT'	=> 'a.*, b.hash, b.text, c.username',
            'FROM'		=> [ $this->tbl_locker_usage => 'a', ],
            'LEFT_JOIN'	=> [
                [
                    'FROM'	=> [$this->tbl_locker => 'b'],
                    'ON'	=> 'a.locker_id=b.id',
                ],
                [
                    'FROM'	=> [ USERS_TABLE => 'c'],
                    'ON'	=> 'a.user_id=c.user_id',
                ],
            ],
            'WHERE'		=> $where,
            // 'ORDER_BY' => $order_by,
        ];
        $sql = $this->db->sql_build_query('SELECT', $sql_array);
        $result = $this->db->sql_query($sql);
        $rowset = $this->db->sql_fetchrowset($result);
        $this->db->sql_freeresult($result);
        return $rowset;
    }/*}}}*/

    private function respond_common_thanks($hash_string)/*{{{*/
    {
        $this->sauth->reject_non_dev('Error Code: 9c5bd80112');
        $rowset = $this->get_common_thanks($hash_string);
        $rowset = $this->process_common_thanks($rowset);
        foreach($rowset as $row)
        {
            $data = [];
            $username = $row['username'];
            $user_id = $row['user_id'];
            $hash = implode(', ', $row['data']);
            $count = $row['count'];
            $n_access = $row['n_access'];
            $data = [
                'username' => $username,
                'count' => $count,
                'n_access' => $n_access,
                'user_id' => $user_id,
                'hash' => $hash];
            $this->template->assign_block_vars('DATA', $data);
        }
        return $this->helper->render('@jeb_locker/common_thanks/base.html');
    }/*}}}*/

}
