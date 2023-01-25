<?php
/**
 *
 * @package phpBB Extension - Mafiascum Hide Email On Registration
 * @copyright (c) 2018 mafiascum.net
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */

namespace mafiascum\mcp\event;

/**
 * @ignore
 */
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
/**
 * Event listener
 */
class main_listener implements EventSubscriberInterface
{
    static public function getSubscribedEvents()
    {
        return array(
			'core.report_post_auth' => 'report_post_auth',
			'core.mcp_reports_report_details_query_after' => 'mcp_reports_report_details_query_after',
			'core.mcp_report_template_data' => 'mcp_report_template_data',
			'core.mcp_reports_modify_post_row' => 'mcp_reports_modify_post_row',
        );
    }

    /**
     * Constructor
     *
     */
    public function __construct(\phpbb\template\template $template, \phpbb\db\driver\driver_interface $db, \phpbb\user $user, \phpbb\auth\auth $auth)
    {
		$this->template = $template;
		$this->db = $db;
		$this->user = $user;
		$this->auth = $auth;
    }

	function mcp_reports_modify_post_row($event) {
		$post_row = $event['post_row'];
		$row = $event['row'];

		$post_row['POST_SUBJECT'] = ($row['topic_title']) ? $row['topic_title'] : $this->user->lang['NO_SUBJECT'];

		$event['post_row'] = $post_row;
	}

	function mcp_report_template_data($event) {
		$report_template = $event['report_template'];
		$post_info = $event['post_info'];

		$report_template['POST_SUBJECT'] = ($post_info['topic_title']) ? $post_info['topic_title'] : $this->user->lang['NO_SUBJECT'];

		$event['report_template'] = $report_template;
	}

	public function report_post_auth($event) {
		//This prevents blocking multiple reports on the same post from being submitted.
		$report_data = $event['report_data'];

		$report_data['post_reported'] = false;

		$event['report_data'] = $report_data;
   }

   function mcp_reports_report_details_query_after($event) {
		//Query all reports for the post & throw into template.
		$sql_ary = $event['sql_ary'];
		$sql_ary['WHERE'] = $sql_ary['WHERE'] . ' AND r.report_closed = 0';
		$sql = $this->db->sql_build_query('SELECT', $sql_ary);
		$result = $this->db->sql_query($sql);

		while($report = $this->db->sql_fetchrow($result)) {
			$report_id = $report['report_id'];
			$reason = array('title' => $report['reason_title'], 'description' => $report['reason_description']);

			if (isset($this->user->lang['report_reasons']['TITLE'][strtoupper($reason['title'])]) && isset($this->user->lang['report_reasons']['DESCRIPTION'][strtoupper($reason['title'])]))
			{
				$reason['description'] = $this->user->lang['report_reasons']['DESCRIPTION'][strtoupper($reason['title'])];
				$reason['title'] = $this->user->lang['report_reasons']['TITLE'][strtoupper($reason['title'])];
			}
			$this->template->assign_block_vars('reportrow', array(
				'REPORT_DATE'				=> $this->user->format_date($report['report_time']),
				'REPORT_REASON_TITLE'		=> $reason['title'],
				'REPORT_REASON_DESCRIPTION'	=> $reason['description'],
				'REPORT_TEXT'				=> $report['report_text'],
				'REPORTER_FULL'				=> get_username_string('full', $report['user_id'], $report['username'], $report['user_colour']),
				'REPORT_ID'					=> $report['report_id'],
			));
		}

		$this->db->sql_freeresult($result);
   }
}