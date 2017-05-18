<?php
/**
 *
 * @package phpBB Extension - tas2580 SEO URLs
 * @copyright (c) 2016 tas2580 (https://tas2580.net)
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */

namespace tas2580\seourls\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event listener
 */
class listener implements EventSubscriberInterface
{
	/** @var \tas2580\seourls\event\base */
	protected $base;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\request\request */
	protected $request;

	/** @var \phpbb\path_helper */
	protected $path_helper;

	/* @var \phpbb\db\driver\factory */
	protected $db;

	/* @var \phpbb\config\config */
	protected $config;

	/* @var \phpbb\auth\auth */
	protected $auth;

	/** @var string phpbb_root_path */
	protected $phpbb_root_path;

	/** @var string php_ext */
	protected $php_ext;

	/**
	 * Constructor
	 *
	 * @param \tas2580\seourls\event\base	$base
	 * @param \phpbb\template\template		$template				Template object
	 * @param \phpbb\request\request		$request				Request object
	 * @param \phpbb\path_helper			$path_helper			Controller helper object
	 * @param \phpbb\db\driver\factory			$db			Database object
	 * @param \phpbb\config\config			$config			Config object
	 * @param \phpbb\auth\auth			$auth			Auth object
	 * @param string						$phpbb_root_path		phpbb_root_path
	 * @param string						$php_ext				php_ext
	 * @access public
	 */
	public function __construct(\tas2580\seourls\event\base $base, \phpbb\template\template $template, \phpbb\request\request $request, \phpbb\path_helper $path_helper, \phpbb\db\driver\factory $db, \phpbb\config\config $config, \phpbb\auth\auth $auth, $phpbb_root_path, $php_ext)
	{
		$this->base = $base;
		$this->template = $template;
		$this->request = $request;
		$this->path_helper = $path_helper;
		$this->db = $db;
		$this->config = $config;
		$this->auth = $auth;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $php_ext;

		$this->in_viewtopic = false;
	}

	/**
	 * Assign functions defined in this class to event listeners in the core
	 *
	 * @return array
	 * @static
	 * @access public
	 */
	public static function getSubscribedEvents()
	{
		return array(
			'core.user_setup_after'										=> 'intercept_page_load',
			'core.append_sid'										=> 'append_sid',
			'core.display_forums_modify_sql'						=> 'display_forums_modify_sql',
			'core.display_forums_modify_template_vars'				=> 'display_forums_modify_template_vars',
			'core.display_forums_modify_forum_rows'					=> 'display_forums_modify_forum_rows',
			'core.display_forums_modify_category_template_vars'		=> 'display_forums_modify_category_template_vars',
			'core.generate_forum_nav'								=> 'generate_forum_nav',
			'core.make_jumpbox_modify_tpl_ary'						=> 'make_jumpbox_modify_tpl_ary',				// Not in phpBB
			'core.memberlist_view_profile'							=> 'memberlist_view_profile',
			'core.pagination_generate_page_link'					=> 'pagination_generate_page_link',
			'core.search_modify_tpl_ary'							=> 'search_modify_tpl_ary',
			'core.viewforum_modify_topicrow'						=> 'viewforum_modify_topicrow',
			'core.viewforum_get_topic_data'							=> 'viewforum_get_topic_data',
			'core.viewtopic_assign_template_vars_before'			=> 'viewtopic_assign_template_vars_before',
			'core.viewtopic_before_f_read_check'					=> 'viewtopic_before_f_read_check',
			'core.viewtopic_modify_page_title'						=> 'viewtopic_modify_page_title',
			'core.viewtopic_modify_post_row'						=> 'viewtopic_modify_post_row',
			'core.viewtopic_get_post_data'							=> 'viewtopic_get_post_data',
		);
	}

	/**
	 * Intercept the request at the first opportunity to check if the URL is the correct one.
	 * If it is not, redirect (301) to the real URL.
	 *
	 * @param	object	$event	The event object
	 * @return	null
	 * @access	public
	 */
	public function intercept_page_load($event)
	{
		$script_name = pathinfo($this->request->server('SCRIPT_NAME'), PATHINFO_FILENAME);
		$url_last_chunk = pathinfo($this->request->server('REQUEST_URI'), PATHINFO_FILENAME);

		if ($script_name=='viewforum' || $script_name=='viewtopic'){
			if ($script_name=='viewforum'){
				//We must not redirect because the extension does not handle these cases:
				// - sd/sk/st is set (sort order and filtering in viewforum)
				// - mark is set (marking forums, topics or subforums as read)
				// - watch is set (watching forums)
				// - unwatch is set (unwatching forums)
				if (!$this->request->is_set('sd') && !$this->request->is_set('sk') && !$this->request->is_set('st')
						&& !$this->request->is_set('mark') && !$this->request->is_set('watch') && !$this->request->is_set('unwatch')){
					$forum_id = $this->request->variable('f', 0);
					$start = $this->request->variable('start', 0);
					$sql = "SELECT forum_id, forum_name
						FROM " . FORUMS_TABLE . "
						WHERE forum_id = $forum_id";
					$res = $this->db->sql_query($sql);
					$row = $this->db->sql_fetchrow($res);
					$this->db->sql_freeresult($res);

					$expected_url = $this->base->generate_forum_link($row['forum_id'] , $row['forum_name'], $start);
				}
			}
			else{ //viewtopic
				//We must not redirect because the extension does not handle these cases:
				// - sd/sk/st is set (sort order and filtering in viewtopic)
				// - thanks is set (we are thanking)
				// - bookmark is set (bookmarking topics)
				// - watch is set (watching topics)
				// - unwatch is set (unwatching topics)
				// - view is set (printing topics OR going to first unread post)
				if (!$this->request->is_set('sd') && !$this->request->is_set('sk') && !$this->request->is_set('st')
						&& !$this->request->is_set('thanks') && !$this->request->is_set('bookmark') 
						&& !$this->request->is_set('watch') && !$this->request->is_set('unwatch') && $this->request->variable('view', '', TRUE)!='print'){
					$post_id = $this->request->variable('p', 0);
					$topic_id = $this->request->variable('t', 0);
					$start = $this->request->variable('start', 0);
			
					if ($post_id){
						$sql = "SELECT t.topic_id, t.topic_title, t.forum_id
							FROM " . POSTS_TABLE . " p
							LEFT JOIN " . TOPICS_TABLE . " t ON p.topic_id=t.topic_id
							WHERE p.post_id = $post_id";
						$res = $this->db->sql_query($sql);
						$row = $this->db->sql_fetchrow($res);
						$this->db->sql_freeresult($res);

						if ($row){
							$sql = "SELECT post_id
								FROM " . POSTS_TABLE . "
								WHERE topic_id = " . $row['topic_id'] . " AND (post_visibility=1";
						
							if ($this->auth->acl_get('m_approve', $row['forum_id'])){
								$sql .= ' OR post_visibility=0';
							}
							if ($this->auth->acl_get('m_delete', $row['forum_id'])){
								$sql .= ' OR post_visibility=2';
							}

							$sql.=") ORDER BY post_time";
							$res = $this->db->sql_query($sql);
							$rowset = $this->db->sql_fetchrowset($res);
							$this->db->sql_freeresult($res);

							$found = FALSE;
							$post_position = 0;
							foreach ($rowset as $childrow){
								if ($childrow['post_id']==$post_id){
									$found = TRUE;
									break;
								}
								$post_position++;
							}

							if (!$found){ //Go to first page
								$post_position = 0;
							}

							$per_page = ($this->config['posts_per_page'] <= 0) ? 1 : $this->config['posts_per_page'];
							$start = 0;
							if (($post_position + 1) > $per_page){
								while ($start < $post_position + 1){
									$start += $per_page;
								}
							}
						
							$expected_url = $this->base->generate_topic_link($row['topic_id'] , $row['topic_title'], $start);
						}
					}
					else{
						$sql = "SELECT topic_id, topic_title
							FROM " . TOPICS_TABLE . "
							WHERE topic_id = $topic_id";
						$res = $this->db->sql_query($sql);
						$row = $this->db->sql_fetchrow($res);
						$this->db->sql_freeresult($res);

						$expected_url = $this->base->generate_topic_link($row['topic_id'] , $row['topic_title'], $start);
					}
				}
			}

			if (isset($expected_url) && pathinfo($expected_url, PATHINFO_FILENAME)!=$url_last_chunk){
				header("HTTP/1.1 301 Moved Permanently");
				header("Location: " . $this->config['script_path'] . "/$expected_url");
				exit();
			}
		}
	}

	/**
	 * Correct the path of $viewtopic_url
	 *
	 * @param	object	$event	The event object
	 * @return	null
	 * @access	public
	 */
	public function append_sid($event)
	{
		if ($this->in_viewtopic && preg_match('#./../viewtopic.' . $this->php_ext  . '#', $event['url']))
		{
			$url = '../viewtopic.' . $this->php_ext ;
			$event['url'] = $url;
		}
		if (isset($event['params']['redirect']))
		{
			$params = $event['params'];
			$params['redirect'] = str_replace('..', '.', $event['params']['redirect']);
			$event['params'] = $params;
		}
	}

	/**
	 * Get informations for the last post from Database
	 *
	 * @param	object	$event	The event object
	 * @return	null
	 * @access	public
	 */
	public function display_forums_modify_sql($event)
	{
		$sql_array = $event['sql_ary'];
		$sql_array['LEFT_JOIN'][] = array(
			'FROM' => array(TOPICS_TABLE => 't'),
			'ON' => "f.forum_last_post_id = t.topic_last_post_id"
		);
		$sql_array['SELECT'] .= ', t.topic_title, t.topic_id, t.topic_posts_approved, t.topic_posts_unapproved, t.topic_posts_softdeleted, t.topic_moved_id';
		$event['sql_ary'] = $sql_array;
	}

	/**
	 * Store informations for the last post in forum_rows array
	 *
	 * @param	object	$event	The event object
	 * @return	null
	 * @access	public
	 */
	public function display_forums_modify_forum_rows($event)
	{
		$forum_rows = $event['forum_rows'];
		if ($event['row']['forum_last_post_time'] == $forum_rows[$event['parent_id']]['forum_last_post_time'])
		{
			$forum_rows[$event['parent_id']]['forum_name_last_post'] =$event['row']['forum_name'];
			$forum_rows[$event['parent_id']]['topic_id_last_post'] =$event['row']['topic_id'];
			$forum_rows[$event['parent_id']]['topic_title_last_post'] =$event['row']['topic_title'];
			$forum_rows[$event['parent_id']]['topic_moved_id_last_post'] =$event['row']['topic_moved_id'];
			$event['forum_rows'] = $forum_rows;
		}
	}

	/**
	 * Rewrite links to forums and subforums in forum index
	 * also correct the path of the forum images if we are in a forum
	 *
	 * @param	object	$event	The event object
	 * @return	null
	 * @access	public
	 */
	public function display_forums_modify_template_vars($event)
	{
		$subforums_row = $event['subforums_row'];
		$forum_row = $event['forum_row'];

		// Rewrite URLs of sub forums
		foreach ($subforums_row as $i => $subforum)
		{
			// A little bit a dirty way, but there is no better solution
			$query = str_replace('&amp;', '&', parse_url($subforum['U_SUBFORUM'], PHP_URL_QUERY));
			parse_str($query, $id);
			$subforums_row[$i]['U_SUBFORUM'] = append_sid($this->base->generate_forum_link($id['f'], $subforum['SUBFORUM_NAME']));
		}

		// Update the image source in forums
		$img = $this->path_helper->update_web_root_path($forum_row['FORUM_IMAGE_SRC']);
		$forum_row['FORUM_IMAGE'] = preg_replace('#img src=\"(.*)\" alt#', 'img src="' . $img . '" alt', $forum_row['FORUM_IMAGE']);

		// Rewrite links to topics, posts and forums
		$replies = $this->base->get_count('topic_posts', $event['row'], $event['row']['forum_id']) - 1;
		$url = $this->base->generate_topic_link(($event['row']['topic_moved_id_last_post'] != 0 ? $event['row']['topic_moved_id_last_post'] : $event['row']['topic_id_last_post']), $event['row']['topic_title_last_post']);
		$forum_row['U_LAST_POST'] = append_sid($this->base->generate_lastpost_link($replies, $url) . '#p' . $event['row']['forum_last_post_id']);
		$forum_row['U_VIEWFORUM'] = append_sid($this->base->generate_forum_link($forum_row['FORUM_ID'], $forum_row['FORUM_NAME']));
		$forum_row['U_NEWEST_POST'] = $url . '?view=unread#unread';

		$event['subforums_row'] = $subforums_row;
		$event['forum_row'] = $forum_row;
	}

	/**
	 * Rewrite the categorie links
	 *
	 * @param	object	$event	The event object
	 * @return	null
	 * @access	public
	 */
	public function display_forums_modify_category_template_vars($event)
	{
		$cat_row = $event['cat_row'];
		$row = $event['row'];
		$cat_row['U_VIEWFORUM'] = append_sid($this->base->generate_forum_link($row['forum_id'], $row['forum_name']));
		$event['cat_row'] = $cat_row;
	}

	/**
	 * Rewrite links in breadcrumbs
	 *
	 * @param	object	$event	The event object
	 * @return	null
	 * @access	public
	 */
	public function generate_forum_nav($event)
	{
		$forum_data = $event['forum_data'];
		$navlinks = $event['navlinks'];
		$navlinks_parents = $event['navlinks_parents'];

		foreach ($navlinks_parents as $id => $data)
		{
			$navlinks_parents[$id]['U_VIEW_FORUM'] = append_sid($this->base->generate_forum_link($data['FORUM_ID'] , $data['FORUM_NAME']));
		}

		$navlinks['U_VIEW_FORUM'] = append_sid($this->base->generate_forum_link($forum_data['forum_id'], $forum_data['forum_name']));
		$event['navlinks'] = $navlinks;
		$event['navlinks_parents'] = $navlinks_parents;
	}

	// Not in phpBB
	public function make_jumpbox_modify_tpl_ary($event)
	{
		$tpl_ary = $event['tpl_ary'];
		$row = $event['row'];
		foreach ($tpl_ary as $id => $data)
		{
			$tpl_ary[$id]['LINK']	 = append_sid($this->base->generate_forum_link($row['forum_id'], $row['forum_name']));
		}

		$event['tpl_ary'] = $tpl_ary;
	}

	/**
	 * Rewrite links to most active forum and topic on profile page
	 *
	 * @param	object	$event	The event object
	 * @return	null
	 * @access	public
	 */
	public function memberlist_view_profile($event)
	{
		$data = $event['member'];
		$this->template->assign_vars(array(
			'U_ACTIVE_FORUM' => $this->base->generate_forum_link($data['active_f_row']['forum_id'], $data['active_f_row']['forum_name'], 0, true),
			'U_ACTIVE_TOPIC' => $this->base->generate_topic_link($data['active_t_row']['topic_id'], $data['active_t_row']['topic_title'], 0, true),
		));
	}

	/**
	 * Rewrite pagination links
	 *
	 * @param	object	$event	The event object
	 * @return	null
	 * @access	public
	 */
	public function pagination_generate_page_link($event)
	{
		// If we have a sort key we do not rewrite the URL
		$query = str_replace('&amp;', '&', parse_url($event['base_url'], PHP_URL_QUERY));
		parse_str($query, $param);
		if (isset($param['sd']) || isset($param['sk']) || isset($param['st']))
		{
			return;
		}

		$start = (($event['on_page'] - 1) * $event['per_page']);
		if (!empty($this->topic_data) && isset($param['f']) && isset($param['t']))
		{
			$event['generate_page_link_override'] = append_sid($this->base->generate_topic_link($this->topic_data['topic_id'], $this->topic_data['topic_title'], $start));
		}
		else if (!empty($this->forum_data) && isset($param['f']))
		{
			$event['generate_page_link_override'] = append_sid($this->base->generate_forum_link($this->forum_data['forum_id'], $this->forum_data['forum_name'], $start));
		}
	}

	/**
	 * Rewrite links in the search result
	 *
	 * @param	object	$event	The event object
	 * @return	null
	 * @access	public
	 */
	public function search_modify_tpl_ary($event)
	{
		$replies = $this->base->get_count('topic_posts', $event['row'], $event['row']['forum_id']) - 1;
		$u_view_topic = $this->base->generate_topic_link($event['row']['topic_id'], $event['row']['topic_title']);

		$tpl_ary = $event['tpl_ary'];
		$tpl_ary['U_LAST_POST'] = append_sid($this->base->generate_lastpost_link($replies, $u_view_topic) . '#p' . $event['row']['topic_last_post_id']);
		$tpl_ary['U_VIEW_TOPIC'] = append_sid($u_view_topic);
		$tpl_ary['U_VIEW_FORUM'] = append_sid($this->base->generate_forum_link($event['row']['forum_id'], $event['row']['forum_name']));
		$tpl_ary['U_NEWEST_POST'] = $u_view_topic . '?view=unread#unread';

		$event['tpl_ary'] = $tpl_ary;
	}

	/**
	 * Rewrite links to topics in forum view
	 *
	 * @param	object	$event	The event object
	 * @return	null
	 * @access	public
	 */
	public function viewforum_modify_topicrow($event)
	{
		// assign to be used in pagination_generate_page_link
		$this->topic_data = array(
			'forum_id' => $event['topic_row']['FORUM_ID'],
			'forum_name' => $event['topic_row']['FORUM_NAME'],
			'topic_id' => $event['topic_row']['TOPIC_ID'],
			'topic_title' => $event['topic_row']['TOPIC_TITLE']
		);

		$topic_row = $event['topic_row'];

		$u_view_topic = $this->base->generate_topic_link($topic_row['TOPIC_ID'], $topic_row['TOPIC_TITLE']);
		$topic_row['U_VIEW_TOPIC'] = append_sid($u_view_topic);
		$topic_row['U_VIEW_FORUM'] = append_sid($this->base->generate_forum_link($topic_row['FORUM_ID'], $topic_row['FORUM_NAME']));
		$topic_row['U_LAST_POST'] = append_sid($this->base->generate_lastpost_link($event['topic_row']['REPLIES'], $u_view_topic) . '#p' . $event['row']['topic_last_post_id']);
		$topic_row['U_NEWEST_POST'] = $u_view_topic . '?view=unread#unread';

		$event['topic_row'] = $topic_row;
	}

	/**
	 * Rewrite the canonical URL on viewforum.php
	 *
	 * @param	object	$event	The event object
	 * @return	null
	 * @access	public
	 */
	public function viewforum_get_topic_data($event)
	{
		// assign to be used in pagination_generate_page_link
		$this->forum_data = array(
			'forum_id' => $event['forum_data']['forum_id'],
			'forum_name' => $event['forum_data']['forum_name']
		);

		$start = $this->request->variable('start', 0);
		$this->template->assign_vars(array(
			'U_VIEW_FORUM'		=> append_sid($this->base->generate_forum_link($event['forum_data']['forum_id'], $event['forum_data']['forum_name'], $start)),
			'U_CANONICAL'		=> $this->base->generate_forum_link($event['forum_data']['forum_id'], $event['forum_data']['forum_name'], $start, true),
		));
	}

	/**
	 * Rewrite the topic URL for the headline of the topic page and the link back to forum
	 *
	 * @param	object	$event	The event object
	 * @return	null
	 * @access	public
	 */
	public function viewtopic_get_post_data($event)
	{
		$data = $event['topic_data'];
		$this->template->assign_vars(array(
			'U_VIEW_TOPIC'		=> append_sid($this->base->generate_topic_link($event['topic_id'], $data['topic_title'], $event['start'])),
			'U_VIEW_FORUM'		=> append_sid($this->base->generate_forum_link($event['forum_id'] , $data['forum_name'])),
			'S_POLL_ACTION'		=> append_sid($this->base->generate_topic_link($event['topic_id'], $data['topic_title'], $event['start'])),
		));
	}

	/**
	 * Assign topic data to global variables for pagination
	 *
	 * @param	object	$event	The event object
	 * @return	null
	 * @access	public
	 */
	public function viewtopic_assign_template_vars_before($event)
	{
		// assign to be used in pagination_generate_page_link
		$this->topic_data = array(
			'forum_id' => $event['topic_data']['forum_id'],
			'forum_name' => $event['topic_data']['forum_name'],
			'topic_id' => $event['topic_data']['topic_id'],
			'topic_title' => $event['topic_data']['topic_title']
		);
	}

	public function viewtopic_before_f_read_check()
	{
		$this->in_viewtopic = true;
	}

	/**
	 * Rewrite the canonical URL on viewtopic.php
	 *
	 * @param	object	$event	The event object
	 * @return	null
	 * @access	public
	 */
	public function viewtopic_modify_page_title($event)
	{
		$start = $this->request->variable('start', 0);
		$data = $event['topic_data'];
		$this->template->assign_vars(array(
			'U_CANONICAL'		=> $this->base->generate_topic_link($data['topic_id'], $data['topic_title'], $start, true),
		));
	}

	/**
	 * Rewrite mini post img link
	 *
	 * @param	object	$event	The event object
	 * @return	null
	 * @access	public
	 */
	public function viewtopic_modify_post_row($event)
	{
		$row = $event['post_row'];
		$start = $this->request->variable('start', 0);
		$data = $event['topic_data'];
		$row['U_MINI_POST'] = append_sid($this->base->generate_topic_link($data['topic_id'], $data['topic_title'], $start) . '#p' . $event['row']['post_id']);
		$event['post_row'] = $row;
	}
}
