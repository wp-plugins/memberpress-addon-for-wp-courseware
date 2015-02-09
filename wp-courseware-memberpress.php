<?php
/*
 * Plugin Name: WP Courseware - MemberPress Add On
 * Version: 1.1
 * Plugin URI: http://flyplugins.com
 * Description: The official extension for WP Courseware to add support for the MemberPress membership plugin for WordPress.
 * Author: Fly Plugins
 * Author URI: http://flyplugins.com
 */

// Main parent class
include_once 'class_members.inc.php';

// Hook to load the class
add_action('init', 'WPCW_MemberPress_init',1);

/**
 * Initialize the membership plugin, only loaded if WP Courseware 
 * exists and is loading correctly.
 */
function WPCW_MemberPress_init()
{
	$item = new WPCW_Members_MemberPress();
	
	// Check for WP Courseware
	if (!$item->found_wpcourseware()) {
		$item->attach_showWPCWNotDetectedMessage();
		return;
	}
	
	// Not found the membership tool
	if (!$item->found_membershipTool()) {
		$item->attach_showToolNotDetectedMessage();
		return;
	}
	
	// Found the tool and WP Courseware, attach.
	$item->attachToTools();
}


/**
 * Membership class that handles the specifics of the MembersPress WordPress plugin and
 * handling the data for levels for that plugin.
 */
class WPCW_Members_MemberPress extends WPCW_Members
{
	const GLUE_VERSION  = 1.00; 
	const EXTENSION_NAME = 'MemberPress';
	const EXTENSION_ID = 'WPCW_memberpress';
	
	/**
	 * Main constructor for this class.
	 */
	function __construct()
	{
		// Initialise using the parent constructor 
		parent::__construct(WPCW_Members_MemberPress::EXTENSION_NAME, WPCW_Members_MemberPress::EXTENSION_ID, WPCW_Members_MemberPress::GLUE_VERSION);
	}
	
	
	/**
	 * Get the membership levels for MemberPress.
	 */
	protected function getMembershipLevels()
	{
	
	//Define $args for post query
	$args=array(
  		'post_type' => 'memberpressproduct',
  		'post_status' => 'publish',
  		'numberposts' => -1
	);

	$levelData = get_posts($args);
	
		if ($levelData && count($levelData) > 0)
		{
			$levelDataStructured = array();
			
			// Format the data in a way that we expect and can process
			foreach ($levelData as $levelDatum)
			{
				
				$levelItem = array();
				$levelItem['name'] 	= $levelDatum->post_title;
				$levelItem['id'] 	= $levelDatum->ID;
				$levelItem['raw'] 	= $levelDatum;
				
				$levelDataStructured[$levelItem['id']] = $levelItem;	
			}
			return $levelDataStructured;
		}
		return false;
	}
	
	
	/**
	 * Function called to attach hooks for handling when a user is updated or created.
	 */	
	
	protected function attach_updateUserCourseAccess()
	{
		// Events called whenever the user levels are changed, which updates the user access.
		add_action('mepr-txn-store', 		array($this, 'handle_updateUserCourseAccess'),10,1);
		add_action('mepr-subscr-store', 		array($this, 'handle_updateUserCourseAccess'),10,1);
	}

		/**
	 * Assign selected courses to members of a paticular level.
	 * @param members of $level_ID will get course enrollment adjusted
	 */
	protected function retroactive_assignment($level_ID)
    {
		global $wpdb;

		$mepr_db = new MeprDb();
		$page = new PageBuilder(false);
		/*
		* Query db for transaction IDs and user IDs for transactions
		* that are in a pending and complete state
		*/
		$query = "SELECT user_id,id
		      FROM {$mepr_db->transactions}
			  WHERE product_id = $level_ID
			  AND (status = 'pending' OR status = 'complete')";

		$results = $wpdb->get_results($query);

		if ($results){

			//get user's assigned products and enroll them into courses accordingly
			foreach ($results as $result){
				
				$userid = $result->user_id;
				$user = new MeprUser($userid);
				$productList = $user->active_product_subscriptions('ids', true);
			
				parent::handle_courseSync($userid, $productList);
			}

		$page->showMessage(__('All members were successfully retroactively enrolled into the selected courses.', 'wp_courseware'));
            
        return;

		}else {
            $page->showMessage(__('No existing customers found for the specified product.', 'wp_courseware'));
        }
		
    }

	/**
	 * Function just for handling the membership callback, to interpret the parameters
	 * for the class to take over.
	 */
	public function handle_updateUserCourseAccess($obj)
	{
		// Get user data
		$user = new MeprUser($obj->user_id);

		// Get product list for user
		$productList = $user->active_product_subscriptions('ids', true);//Returns an array of Product ID's the user has purchased and is paid up on.
		
		// Get user ID
		$userid = $obj->user_id;

		// Over to the parent class to handle the sync of data.
		parent::handle_courseSync($userid, $productList);
	}
		
	
	/**
	 * Detect presence of the membership plugin.
	 */
	public function found_membershipTool()
	{
		return function_exists('mepr_plugin_info');
	}
}
?>