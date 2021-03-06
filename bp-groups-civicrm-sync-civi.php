<?php

/**
 * BP Groups CiviCRM Sync CiviCRM Class.
 *
 * A class that encapsulates CiviCRM functionality.
 *
 * Code snippet preserved from previous docblock:
 *
 * $groupOptions = CRM_Core_BAO_OptionValue::getOptionValuesAssocArrayFromName('group_type');
 * $groupTypes = CRM_Core_OptionGroup::values('group_type', TRUE);
 *
 * @since 0.1
 */
class BP_Groups_CiviCRM_Sync_CiviCRM {



	/**
	 * Plugin object.
	 *
	 * @since 0.1
	 * @access public
	 * @var object $parent_obj The plugin object
	 */
	public $parent_obj;



	/**
	 * BuddyPress utilities object.
	 *
	 * @since 0.1
	 * @access public
	 * @var object $bp The BuddyPress utilities object
	 */
	public $bp;



	/**
	 * Admin utilities object.
	 *
	 * @since 0.1
	 * @access public
	 * @var object $admin The Admin utilities object
	 */
	public $admin;



	/**
	 * Flag for overriding sync process.
	 *
	 * @since 0.1
	 * @access public
	 * @var bool $do_not_sync Flag for overriding sync process
	 */
	public $do_not_sync = false;



	/**
	 * Initialise this object.
	 *
	 * @since 0.1
	 *
	 * @param object $parent_obj The parent object
	 */
	function __construct( $parent_obj ) {

		// store reference to parent
		$this->parent_obj = $parent_obj;

		// add actions for plugin init on CiviCRM init
		add_action( 'civicrm_instance_loaded', array( $this, 'register_hooks' ) );

	}



	/**
	 * Set references to other objects.
	 *
	 * @since 0.1
	 *
	 * @param object $bp_object Reference to this plugin's BP object
	 * @param object $admin_object Reference to this plugin's Admin object
	 */
	public function set_references( &$bp_object, &$admin_object ) {

		// store BuddyPress reference
		$this->bp = $bp_object;

		// store Admin reference
		$this->admin = $admin_object;

	}



	/**
	 * Register hooks on plugin init.
	 *
	 * @since 0.1
	 */
	public function register_hooks() {

		// allow plugins to register php and template directories
		add_action( 'civicrm_config', array( $this, 'register_directories' ), 10, 1 );

		// intercept CiviCRM group create form
		//add_action( 'civicrm_buildForm', array( $this, 'form_create_bp_group_options' ), 10, 2 );

		// intercept CiviCRM group create form submission
		//add_action( 'civicrm_postProcess', array( $this, 'form_create_bp_group_process' ), 10, 2 );

		// intercept CiviCRM Drupal Organic Group edit form
		add_action( 'civicrm_buildForm', array( $this, 'form_edit_og_options' ), 10, 2 );

		// intercept CiviCRM Drupal Organic Group edit form submission
		add_action( 'civicrm_postProcess', array( $this, 'form_edit_og_process' ), 10, 2 );

		// intercept CiviCRM's add contacts to group
		add_action( 'civicrm_pre', array( $this, 'group_contacts_added' ), 10, 4 );

		// intercept CiviCRM's delete contacts from group
		add_action( 'civicrm_pre', array( $this, 'group_contacts_deleted' ), 10, 4 );

		// intercept CiviCRM's rejoin contacts to group
		add_action( 'civicrm_pre', array( $this, 'group_contacts_rejoined' ), 10, 4 );

		// broadcast to others
		do_action( 'bp_groups_civicrm_sync_civi_loaded' );

	}



	/**
	 * Test if CiviCRM plugin is active.
	 *
	 * @since 0.1
	 *
	 * @return bool
	 */
	public function is_active() {

		// bail if no CiviCRM init function
		if ( ! function_exists( 'civi_wp' ) ) return false;

		// try and init CiviCRM
		return civi_wp()->initialize();

	}



	/**
	 * Register directories that CiviCRM searches for php and template files.
	 *
	 * @since 0.1
	 *
	 * @param object $config The CiviCRM config object
	 */
	public function register_directories( &$config ) {

		// define our custom path
		$custom_path = BP_GROUPS_CIVICRM_SYNC_PATH . 'civicrm_custom_templates';

		// kick out if no CiviCRM
		if ( ! civi_wp()->initialize() ) return;

		// get template instance
		$template = CRM_Core_Smarty::singleton();

		// add our custom template directory
		$template->addTemplateDir( $custom_path );

		// register template directories
		$template_include_path = $custom_path . PATH_SEPARATOR . get_include_path();
		set_include_path( $template_include_path );

	}



	//##########################################################################



	/**
	 * Creates the CiviCRM Group which is the ultimate parent for all BuddyPress groups.
	 *
	 * @since 0.1
	 */
	public function meta_group_create() {

		// init or die
		if ( ! $this->is_active() ) return;

		// init transaction
		$transaction = new CRM_Core_Transaction();



		// don't die
		$abort = false;

		// get the group ID of the CiviCRM meta group
		$civi_meta_group_id = $this->find_group_id(
			$this->meta_group_get_source(),
			null,
			$abort
		);

		// skip if it already exists
		if ( is_numeric( $civi_meta_group_id ) AND $civi_meta_group_id > 0 ) {

			// skip

		} else {

			// define group
			$params = array(
				'name' => __( 'BuddyPress Groups', 'bp-groups-civicrm-sync' ),
				'title' => __( 'BuddyPress Groups', 'bp-groups-civicrm-sync' ),
				'description' => __( 'Container for all BuddyPress Groups', 'bp-groups-civicrm-sync' ),
				'is_active' => 1,
			);

			// set inscrutable group type (Access Control)
			$params['group_type'] = array( '1' => 1 );

			// get "source" for the CiviCRM group
			$params['source'] = $this->meta_group_get_source();

			// use our adapted version of CRM_Bridge_OG_Drupal::updateCiviGroup()
			$this->create_group( $params );

		}

		// assign groups with no parent to the meta group
		//$this->meta_group_groups_assign();

		// do the database transaction
	    $transaction->commit();

	}



	/**
	 * Deletes the CiviCRM Group which is the ultimate parent for all BuddyPress groups.
	 *
	 * @since 0.1
	 */
	public function meta_group_delete() {

		// init or die
		if ( ! $this->is_active() ) return;

		// don't die
		$abort = false;

		// get the group ID of the CiviCRM meta group
		$civi_meta_group_id = $this->find_group_id(
			$this->meta_group_get_source(),
			null,
			$abort
		);

		// if it exists
		if ( is_numeric( $civi_meta_group_id ) AND $civi_meta_group_id > 0 ) {

			// init transaction
			$transaction = new CRM_Core_Transaction();

			// delete group
			CRM_Contact_BAO_Group::discard( $civi_meta_group_id );

			// do the database transaction
			$transaction->commit();

		}

	}



	/**
	 * Assign all CiviCRM Groups with no parent to our meta group.
	 *
	 * @since 0.1
	 */
	public function meta_group_groups_assign() {

		// init or die
		if ( ! $this->is_active() ) return;

		// don't die
		$abort = false;

		// get the group ID of the CiviCRM meta group
		$civi_meta_group_id = $this->find_group_id(
			$this->meta_group_get_source(),
			null,
			$abort
		);

		// define get "all with no parent" params
		$params = array(
			'version' => 3,
			// define stupidly high limit, because API defaults to 25
			'options' => array(
				'limit' => '10000',
			),
		);

		// get all groups with no parent ID (get ALL for now)
		$all_groups = civicrm_api( 'group', 'get', $params );

		// if we got some groups
		if (
			$all_groups['is_error'] == 0 AND
			isset( $all_groups['values'] ) AND
			count( $all_groups['values'] ) > 0
		) {

			// loop
			foreach( $all_groups['values'] AS $group ) {

				// when there is no parent
				if (
					! isset( $group['parents'] ) OR
					is_null( $group['parents'] ) OR
					$group['parents'] == ''
				) {

					// init transaction
					//$transaction = new CRM_Core_Transaction();

					// set BP group to empty, which triggers assignment to meta group
					$bp_parent_id = 0;

					// exclude container group
					if ( isset( $group['id'] ) AND $group['id'] == $civi_meta_group_id ) continue;

					// if "source" is not present, it's not an OG/BP group
					if ( ! isset( $group['source'] ) OR is_null( $group['source'] ) ) continue;

					// get group type
					$group_type = $this->civi_group_get_code_by_source( $group['source'] );

					// get CiviCRM group ID for BP parent group
					$civi_parent_id = $this->get_civi_parent_group_id( $bp_parent_id, $group_type );

					// create nesting
					$this->group_nesting_create( $group['id'], $civi_parent_id, $group_type );

					// retain CiviCRM group type
					$group['group_type'] = $this->civi_group_get_type_by_code( $group_type );

					// update the group
					$this->update_group( $group );

					// do the database transaction
					//$transaction->commit();

				}

			}

		} else {

			// debug
			error_log( print_r( array(
				'method' => __METHOD__,
				'all_groups' => $all_groups,
				'backtrace' => wp_debug_backtrace_summary(),
			), true ) );

		}

	}



	/**
	 * Remove all top-level CiviCRM Groups from the meta group.
	 *
	 * @since 0.1
	 */
	public function meta_group_groups_remove() {

		// init or die
		if ( ! $this->is_active() ) return;

		// don't die
		$abort = false;

		// get the group ID of the CiviCRM meta group
		$civi_meta_group_id = $this->find_group_id(
			$this->meta_group_get_source(),
			null,
			$abort
		);

		// define get "all with no parent" params
		$params = array(
			'version' => 3,
			// define stupidly high limit, because API defaults to 25
			'options' => array(
				'limit' => '10000',
			),
		);

		// get all groups with no parent ID (get ALL for now)
		$all_groups = civicrm_api( 'group', 'get', $params );

		// if we got some groups
		if (
			$all_groups['is_error'] == 0 AND
			isset( $all_groups['values'] ) AND
			count( $all_groups['values'] ) > 0
		) {

			// loop
			foreach( $all_groups['values'] AS $group ) {

				// when there is a parent
				if ( isset( $group['parents'] ) AND ! empty( $group['parents'] ) ) {

					// if "source" is not present, it's not an OG/BP group
					if ( ! isset( $group['source'] ) OR is_null( $group['source'] ) ) continue;

					// skip if the parent is not the container group
					if ( $group['parents'] != $civi_meta_group_id ) continue;

					// init transaction
					//$transaction = new CRM_Core_Transaction();

					// delete nesting
					$this->group_nesting_delete( $group['id'], $group['parents'] );

					// get group type
					$group_type = $this->civi_group_get_code_by_source( $group['source'] );

					// retain CiviCRM group type
					$group['group_type'] = $this->civi_group_get_type_by_code( $group_type );

					// clear parents
					$group['parents'] = null;

					// do the database transaction
					//$transaction->commit();

					// update the group
					$this->update_group( $group );

				}

			}

		} else {

			// debug
			error_log( print_r( array(
				'method' => __METHOD__,
				'all_groups' => $all_groups,
				'backtrace' => wp_debug_backtrace_summary(),
			), true ) );

		}

	}



	/**
	 * Get source (our unique code) for our meta group.
	 *
	 * @since 0.1
	 *
	 * @return string The unique meta group code
	 */
	public function meta_group_get_source() {

		// define code
		return 'bp-groups-civicrm-sync';

	}



	//##########################################################################



	/**
	 * Creates a CiviCRM Group when a BuddyPress group is created.
	 *
	 * @since 0.1
	 *
	 * @param int $bp_group_id the numeric ID of the BP group
	 * @param object $bp_group The BP group object
	 * @return array $return Associative array of CiviCRM group IDs (member_group_id, acl_group_id)
	 */
	public function create_civi_group( $bp_group_id, $bp_group ) {

		// are we overriding this?
		if ( $this->do_not_sync ) return false;

		// init or die
		if ( ! $this->is_active() ) return false;

		// init return
		$return = array();



		// init transaction
		$transaction = new CRM_Core_Transaction();

		// define group
		$params = array(
			'bp_group_id' => $bp_group_id,
			'name' => stripslashes( $bp_group->name ),
			'title' => stripslashes( $bp_group->name ),
			'description' => stripslashes( $bp_group->description ),
			'is_active' => 1,
		);



		// first create the CiviCRM group
		$group_params = $params;

		// get name for the CiviCRM group
		$group_params['source'] = $this->member_group_get_sync_name( $bp_group_id );

		// define CiviCRM group type (Mailing List by default)
		$group_params['group_type'] = $this->civi_group_get_type_by_code( 'member' );

		// use our adapted version of CRM_Bridge_OG_Drupal::updateCiviGroup()
		$this->create_group( $group_params );

		// store ID of created CiviCRM group
		$return['member_group_id'] = $group_params['group_id'];



		// next create the CiviCRM ACL group
		$acl_params = $params;

		// set name and title of ACL group
		$acl_params['name'] = $acl_params['title'] = $acl_params['name'] . ': Administrator';

		// set source name for ACL group
		$acl_params['source'] = $this->acl_group_get_sync_name( $bp_group_id );

		// set inscrutable group type (Access Control)
		$acl_params['group_type'] = $this->civi_group_get_type_by_code( 'acl' );

		// create the ACL group too
		$this->create_group( $acl_params );

		// set some further params
		$acl_params['acl_group_id'] = $acl_params['group_id'];
		$acl_params['civicrm_group_id'] = $group_params['group_id'];

		// use cloned CiviCRM function
		$this->updateCiviACLTables( $acl_params, 'add' );

		// store ID of created CiviCRM group
		$return['acl_group_id'] = $acl_params['group_id'];



		// create nesting with no parent
		$this->group_nesting_update( $bp_group_id, 0 );



		// do the database transaction
	    $transaction->commit();


		// add the creator to the groups
		$params = array(
			'bp_group_id' => $bp_group_id,
			'uf_id' => $bp_group->creator_id,
			'is_active' => 1,
			'is_admin' => 1,
		);

		// use clone of CRM_Bridge_OG_Drupal::og()
		$this->group_contact_sync( $params, 'add' );



		// --<
		return $return;

	}



	/**
	 * Updates a CiviCRM Group when a BuddyPress group is updated.
	 *
	 * @since 0.1
	 *
	 * @param int $group_id The numeric ID of the BP group
	 * @param object $group The BP group object
	 */
	public function update_civi_group( $group_id, $group ) {

		// are we overriding this?
		if ( $this->do_not_sync ) return false;

		// init or die
		if ( ! $this->is_active() ) return false;

		// init return
		$return = array();



		// init transaction
		$transaction = new CRM_Core_Transaction();

		// define group
		$params = array(
			'bp_group_id' => $group_id,
			'name' => stripslashes( $group->name ),
			'title' => stripslashes( $group->name ),
			'description' => stripslashes( $group->description ),
			'is_active' => 1,
		);



		// first update the CiviCRM group
		$group_params = $params;

		// get name for the CiviCRM group
		$group_params['source'] = $this->member_group_get_sync_name( $group_id );

		// define CiviCRM group type (Mailing List by default)
		$group_params['group_type'] = $this->civi_group_get_type_by_code( 'member' );

		// use our adapted version of CRM_Bridge_OG_Drupal::updateCiviGroup()
		$this->update_group( $group_params );

		// store ID of created CiviCRM group
		$return['member_group_id'] = $group_params['group_id'];



		// next update the CiviCRM ACL group
		$acl_params = $params;

		// set name and title of ACL group
		$acl_params['name'] = $acl_params['title'] = $acl_params['name'] . ': Administrator';

		// set source name for ACL group
		$acl_params['source'] = $this->acl_group_get_sync_name( $group_id );

		// set inscrutable group type (Access Control)
		$acl_params['group_type'] = $this->civi_group_get_type_by_code( 'acl' );

		// update the ACL group too
		$this->update_group( $acl_params );

		// set some further params
		$acl_params['acl_group_id'] = $acl_params['group_id'];
		$acl_params['civicrm_group_id'] = $group_params['group_id'];

		// use cloned CiviCRM function
		$this->updateCiviACLTables( $acl_params, 'update' );

		// store ID of created CiviCRM group
		$return['acl_group_id'] = $acl_params['group_id'];



		// do the database transaction
	    $transaction->commit();



		// --<
		return $return;

	}



	/**
	 * Deletes a CiviCRM Group when a BuddyPress group is deleted.
	 *
	 * @since 0.1
	 *
	 * @param int $group_id The numeric ID of the BP group
	 */
	public function delete_civi_group( $group_id ) {

		// are we overriding this?
		if ( $this->do_not_sync ) return;

		// init or die
		if ( ! $this->is_active() ) return;



		// init transaction
		$transaction = new CRM_Core_Transaction();

		// get the group object
		$group = groups_get_group( array( 'group_id' => $group_id ) );

		// define group
		$params = array(
			'bp_group_id' => $group_id,
			'name' => $group->name,
			'title' => $group->name,
			'is_active' => 1,
		);



		// first delete the CiviCRM group
		$group_params = $params;

		// get name for the CiviCRM group
		$group_params['source'] = $this->member_group_get_sync_name( $group_id );

		// use our adapted version of CRM_Bridge_OG_Drupal::updateCiviGroup()
		$this->delete_group( $group_params );



		// next delete the CiviCRM ACL group
		$acl_params = $params;

		// set name and title of ACL group
		$acl_params['name'] = $acl_params['title'] = $acl_params['name'] . ': Administrator';

		// set source name for ACL group
		$acl_params['source'] = $this->acl_group_get_sync_name( $group_id );

		// delete the ACL group too
		$this->delete_group( $acl_params );

		// set some further params
		$acl_params['acl_group_id'] = $acl_params['group_id'];
		$acl_params['civicrm_group_id'] = $group_params['group_id'];

		// use cloned CiviCRM function
		$this->updateCiviACLTables( $acl_params, 'delete' );



		// do the database transaction
	    $transaction->commit();

	}



	//##########################################################################



	/**
	 * Create all CiviCRM Group Nestings.
	 *
	 * @since 0.1
	 */
	public function group_hierarchy_build() {

		// init or die
		if ( ! $this->is_active() ) return;

		// get tree
		$tree = BP_Groups_Hierarchy::get_tree();

		// bail if we don't get one
		if ( empty( $tree ) ) return;

		// loop through tree
		foreach( $tree AS $bp_group ) {

			// update the nesting
			$this->group_nesting_update( $bp_group->id, $bp_group->parent_id );

		}

	}



	/**
	 * Delete all CiviCRM Group Nestings.
	 *
	 * @since 0.1
	 */
	public function group_hierarchy_collapse() {

		// init or die
		if ( ! $this->is_active() ) return;

		// get tree
		$tree = BP_Groups_Hierarchy::get_tree();

		// bail if we don't get one
		if ( empty( $tree ) ) return;

		// loop through tree
		foreach( $tree AS $bp_group ) {

			// collapse nesting by assigning all to meta group
			$this->group_nesting_update( $bp_group->id, 0 );

		}

	}



	/**
	 * Create a CiviCRM Group Nesting.
	 *
	 * @since 0.1
	 *
	 * @param int $civi_group_id The numeric ID of the CiviCRM group
	 * @param int $civi_parent_id The numeric ID of the parent CiviCRM group
	 * @param string $source Group update type - ('member' or 'acl')
	 */
	public function group_nesting_create( $civi_group_id, $civi_parent_id, $source ) {

		// bail if no parent set
		if ( empty( $civi_parent_id ) ) return;
		if ( ! is_numeric( $civi_parent_id ) ) return;

		// init transaction
		//$transaction = new CRM_Core_Transaction();

		// define new group nesting
		$create_params = array(
			'version' => 3,
			'child_group_id' => $civi_group_id,
			'parent_group_id' => $civi_parent_id,
		);

		// create CiviCRM group nesting under meta group
		$create_result = civicrm_api( 'group_nesting', 'create', $create_params );

		// error check
		if ( $create_result['is_error'] == '1' ) {

			// debug
			error_log( print_r( array(
				'method' => __METHOD__,
				'create_params' => $create_params,
				'create_result' => $create_result,
				'backtrace' => wp_debug_backtrace_summary(),
			), true ) );

		}

		// do the database transaction
	    //$transaction->commit();

	}



	/**
	 * Updates a CiviCRM Group's hierarchy when a BuddyPress group's hierarchy is updated.
	 *
	 * @since 0.1
	 *
	 * @param int $bp_group_id The numeric ID of the BP group
	 * @param int $bp_parent_id The numeric ID of the parent BP group
	 */
	public function group_nesting_update( $bp_group_id, $bp_parent_id ) {

		// init or die
		if ( ! $this->is_active() ) return;

		// init transaction
		$transaction = new CRM_Core_Transaction();



		// get group source
		$source = $this->member_group_get_sync_name( $bp_group_id );

		// don't die
		$abort = false;

		// get the group ID of the CiviCRM member group
		$civi_group_id = $this->find_group_id(
			$source,
			null,
			$abort
		);

		// get the group data
		$civi_group = $this->get_civi_group_by_id( $civi_group_id );

		// if there's an existing parent
		if ( isset( $civi_group['parents'] ) AND ! empty( $civi_group['parents'] ) ) {

			// delete existing
			$this->group_nesting_delete( $civi_group_id, $civi_group['parents'] );

		}

		// get CiviCRM group ID for BP group
		$civi_parent_id = $this->get_civi_parent_group_id( $bp_parent_id, 'member' );

		// create new nesting
		$this->group_nesting_create( $civi_group_id, $civi_parent_id, 'member' );



		// define group
		$group_params = array(
			'bp_group_id' => $bp_group_id,
			'id' => $civi_group_id,
			'is_active' => 1,
		);

		// get name for the CiviCRM group
		$group_params['source'] = $source;

		// define CiviCRM group type (Mailing List by default)
		$group_params['group_type'] = $this->civi_group_get_type_by_code( 'member' );

		// use our adapted version of CRM_Bridge_OG_Drupal::updateCiviGroup()
		$this->update_group( $group_params );



		// get ACL source
		$acl_source = $this->acl_group_get_sync_name( $bp_group_id );

		// don't die
		$abort = false;

		// get the group ID of the CiviCRM member group
		$civi_acl_group_id = $this->find_group_id(
			$acl_source,
			null,
			$abort
		);

		// get the ACL group data
		$civi_acl_group = $this->get_civi_group_by_id( $civi_acl_group_id );

		// if there's an existing parent
		if ( isset( $civi_acl_group['parents'] ) AND ! empty( $civi_acl_group['parents'] ) ) {

			// delete existing
			$this->group_nesting_delete( $civi_acl_group_id, $civi_acl_group['parents'] );

		}

		// get CiviCRM group ID for BP group
		$civi_acl_parent_id = $this->get_civi_parent_group_id( $bp_parent_id, 'acl' );

		// create new nesting
		$this->group_nesting_create( $civi_acl_group_id, $civi_acl_parent_id, 'acl' );

		// define group for update
		$group_params = array(
			'bp_group_id' => $bp_group_id,
			'id' => $civi_acl_group_id,
			'is_active' => 1,
		);

		// get name for the CiviCRM ACL group
		$group_params['source'] = $acl_source;

		// define CiviCRM group type (Access Control)
		$group_params['group_type'] = $this->civi_group_get_type_by_code( 'acl' );

		// use our adapted version of CRM_Bridge_OG_Drupal::updateCiviGroup()
		$this->update_group( $group_params );



		// do the database transaction
	    $transaction->commit();



		/*
		// define group
		$params = array(
			'version' => 3,
			'id' => $civi_group_id,
			'is_active' => 1,
		);

		// use API to inspect group
		$group = civicrm_api( 'group', 'get', $params );
		*/

		/*
		if( ! $success ) {
			bp_core_add_message( __( 'There was an error syncing; please try again.', 'bp-groups-civicrm-sync' ), 'error' );
		} else {
			bp_core_add_message( __( 'Group hierarchy settings synced successfully.', 'bp-groups-civicrm-sync' ) );
		}
		*/

	}



	/**
	 * Deletes all CiviCRM Group Nestings for a given Group ID.
	 *
	 * @since 0.1
	 *
	 * @param int $civi_group_id The numeric ID of the CiviCRM Group
	 * @param int $civi_parent_id The numeric ID of the parent CiviCRM Group
	 */
	public function group_nesting_delete( $civi_group_id, $civi_parent_id ) {

		// define existing group nesting
		$existing_params = array(
			'version' => 3,
			'parent_group_id' => $civi_parent_id,
			'child_group_id' => $civi_group_id,
		);

		// get existing group nesting
		$existing_result = civicrm_api( 'group_nesting', 'get', $existing_params );

		// did we get any?
		if ( isset( $existing_result['values'] ) AND count( $existing_result['values'] ) > 0 ) {

			// loop through them
			foreach( $existing_result['values'] AS $existing_group_nesting ) {

				// construct delete array
				$delete_params = array(
					'version' => 3,
					'id' => $existing_group_nesting['id'],
				);

				// clear existing group nesting
				$delete_result = civicrm_api( 'group_nesting', 'delete', $delete_params );

			}

		}

	}



	/**
	 * For a given BuddyPress parent group ID, get the ID of the synced CiviCRM Group.
	 *
	 * @since 0.1
	 *
	 * @param int $bp_parent_id The numeric ID of the parent BP group
	 * @param string $source Group update type - ('member' or 'acl')
	 */
	public function get_civi_parent_group_id( $bp_parent_id, $source ) {

		// init return
		$civi_parent_id = false;

		// if the passed BP parent ID is 0, we're removing the BP parent or none is set
		if ( $bp_parent_id == 0 ) {

			// get our settings
			$parent_group = absint( $this->admin->setting_get( 'parent_group' ) );

			// bail if we're not using a parent group
			if ( $parent_group == 0 ) return false;

			// get meta group or die
			$abort = true;

			// get the group ID of the CiviCRM meta group
			$civi_parent_id = $this->find_group_id(
				$this->meta_group_get_source(),
				null,
				$abort
			);

		} else {

			// get parent or die
			$abort = true;

			// what kind of group is this?
			if ( $source == 'member' ) {
				$name = $this->member_group_get_sync_name( $bp_parent_id );
			} else {
				$name = $this->acl_group_get_sync_name( $bp_parent_id );
			}

			// get the group ID of the parent CiviCRM group
			$civi_parent_id = $this->find_group_id(
				$name,
				null,
				$abort
			);

		}

		// --<
		return $civi_parent_id;

	}



	//##########################################################################



	/**
	 * Creates a CiviCRM Group.
	 *
	 * Unfortunately, CiviCRM insists on assigning the logged-in user as the group creator
	 * This means that we cannot assign the BP group creator as the CiviCRM group creator
	 * except by doing some custom SQL.
	 *
	 * @see CRM_Contact_BAO_Group::create()
	 *
	 * @since 0.1
	 *
	 * @param array $params The array of CiviCRM API params
	 * @param string $group_type The type of CiviCRM Group
	 */
	public function create_group( &$params, $group_type = null ) {

		// do not die on failure
		$abort = false;

		// always use API 3
		$params['version'] = 3;

		// if we have a group type passed here, use it
		if ( ! is_null( $group_type ) ) $params['group_type'] = $group_type;

		// use CiviCRM API to create the group (will update if ID is set)
		$group = civicrm_api( 'group', 'create', $params );

		// how did we do?
		if ( ! civicrm_error( $group ) ) {

			// okay, add it to our params
			$params['group_id'] = $group['id'];

		} else {

			// log identifying data
			error_log( print_r( array(
				'method' => __METHOD__,
				'message' => __( 'Could not create CiviCRM group', 'bp-groups-civicrm-sync' ),
				'params' => $params,
				'group' => $group,
				'backtrace' => wp_debug_backtrace_summary(),
			), true ) );

		}

		// because this is passed by reference, ditch the ID
		unset( $params['id'] );

	}



	/**
	 * Updates a CiviCRM Group or creates it if it doesn't exist.
	 *
	 * @since 0.1
	 *
	 * @param array $params The array of CiviCRM API params
	 * @param string $group_type The type of CiviCRM Group
	 */
	public function update_group( &$params, $group_type = null ) {

		// do not die on failure
		$abort = false;

		// always use API 3
		$params['version'] = 3;

		// if ID not specified, get the CiviCRM group ID from "source" value
		if ( ! isset( $params['id'] ) OR empty( $params['id'] ) ) {

			// hand over to our clone of the CRM_Bridge_OG_Utils::groupID method
			$params['id'] = $this->find_group_id(
				$params['source'],
				null,
				$abort
			);

		}

		// if we have a group type passed here, use it
		if ( ! is_null( $group_type ) ) $params['group_type'] = $group_type;

		// use CiviCRM API to create the group (will update if ID is set)
		$group = civicrm_api( 'group', 'create', $params );

		// how did we do?
		if ( ! civicrm_error( $group ) ) {

			// okay, add it to our params
			$params['group_id'] = $group['id'];

		} else {

			// log identifying data
			error_log( print_r( array(
				'method' => __METHOD__,
				'message' => __( 'Could not update CiviCRM group', 'bp-groups-civicrm-sync' ),
				'params' => $params,
				'group' => $group,
				'backtrace' => wp_debug_backtrace_summary(),
			), true ) );

		}

		// because this is passed by reference, ditch the ID
		unset( $params['id'] );

	}



	/**
	 * Deletes a CiviCRM Group.
	 *
	 * @since 0.1
	 *
	 * @param array $params The array of CiviCRM API params
	 */
	public function delete_group( &$params ) {

		// do not die on failure
		$abort = false;

		// always use API 3
		$params['version'] = 3;

		// if ID not specified, get the CiviCRM group ID from "source" value
		if ( ! isset( $params['id'] ) OR empty( $params['id'] ) ) {

			// hand over to our clone of the CRM_Bridge_OG_Utils::groupID method
			$params['id'] = $this->find_group_id(
				$params['source'],
				null,
				$abort
			);

		}

		// delete the group only if we have a valid id
		if ( $params['id'] ) {

			// delete group
			CRM_Contact_BAO_Group::discard( $params['id'] );

			// assign group ID
	        $params['group_id'] = $params['id'];

		}

		// because this is passed by reference, ditch the ID
		unset( $params['id'] );

	}



	//##########################################################################



	/**
	 * Add a CiviCRM contact to a CiviCRM group.
	 *
	 * @since 0.1
	 *
	 * @param integer $civi_group_id The ID of the CiviCRM group
	 * @param array $civi_contact_id The numeric ID of a CiviCRM contact
	 * @return array $group_contact
	 */
	public function group_contact_create( $civi_group_id, $civi_contact_id ) {

		// init API params
		$params = array(
			'version' => 3,
			'contact_id' => $civi_contact_id,
			'group_id' => $civi_group_id,
			'status' => 'Added',
		);

		// call API
		$group_contact = civicrm_api( 'GroupContact', 'Create', $params );

		// --<
		return $group_contact;

	}



	/**
	 * Delete a CiviCRM contact from a CiviCRM group.
	 *
	 * @since 0.1
	 *
	 * @param integer $civi_group_id The ID of the CiviCRM group
	 * @param array $civi_contact_id The numeric ID of a CiviCRM contact
	 * @return array $group_contact
	 */
	public function group_contact_delete( $civi_group_id, $civi_contact_id ) {

		// init API params
		$params = array(
			'version' => 3,
			'contact_id' => $civi_contact_id,
			'group_id' => $civi_group_id,
			'status' => 'Deleted',
		);

		// call API
		$group_contact = civicrm_api( 'GroupContact', 'Delete', $params );

		// --<
		return $group_contact;

	}



	/**
	 * Sync group member.
	 *
	 * @since 0.1
	 *
	 * @param array $params The array of CiviCRM API params
	 * @param string $op The type of CiviCRM operation
	 */
	function group_contact_sync( &$params, $op ) {

		// get the CiviCRM contact ID
		$civi_contact_id = $this->get_contact_id( $params['uf_id'] );

		// if we don't get one
		if ( ! $civi_contact_id ) {

			// what to do?
			if ( BP_GROUPS_CIVICRM_SYNC_DEBUG ) {

				// construct verbose error string
				$error = sprintf(
					__( 'No CiviCRM Contact ID could be found for WordPress User ID %d', 'bp-groups-civicrm-sync' ),
					$user_id
				);

				// log something
				error_log( print_r( array(
					'method' => __METHOD__,
					'error' => $error,
					'user' => print_r( new WP_User( $user_id ), true ),
					'backtrace' => wp_debug_backtrace_summary(),
				), true ) );

			}

			// bail
			return;

		}

		// die if not found
		$abort = true;

		// no title please
		$title = null;

		// get the CiviCRM group ID of this BuddyPress group
		$civi_group_id = $this->find_group_id(
			$this->member_group_get_sync_name( $params['bp_group_id'] ),
			$title,
			$abort
		);

		// init CiviCRM group params
		$groupParams = array(
			'contact_id' => $civi_contact_id,
			'group_id' => $civi_group_id,
			'version' => 3,
		);

		// do the operation on the group
		if ($op == 'add') {
			$groupParams['status'] = $params['is_active'] ? 'Added' : 'Pending';
			$group_contact = civicrm_api( 'GroupContact', 'Create', $groupParams );
		} else {
			$groupParams['status'] = 'Removed';
			$group_contact = civicrm_api( 'GroupContact', 'Delete', $groupParams );
		}

		// do we have an admin user?
		if ( isset( $params['is_admin'] ) AND ! is_null( $params['is_admin'] ) ) {

			// get the group ID of the acl group
			$civi_group_id = $this->find_group_id(
				$this->acl_group_get_sync_name( $params['bp_group_id'] ),
				$title,
				$abort
			);

			// define params
			$groupParams = array(
				'contact_id' => $civi_contact_id,
				'group_id' => $civi_group_id,
				'status' => $params['is_admin'] ? 'Added' : 'Removed',
				'version' => 3,
			);

			// either add or remove, depending on role
			if ( $params['is_admin'] ) {
				$acl_group_contact = civicrm_api( 'GroupContact', 'Create', $groupParams );
			} else {

				/**
				 * Unfortunately this will create a record that the contact was a member
				 * of the ACL Group but has been removed - even if they have *never been*
				 * a member of the group.
				 *
				 * Ideally the CiviCRM API should find out if the user was a member before
				 * registering the deletion event, but we may have to check the membership
				 * manually beforehand and skip this API call if the contact is not a member.
				 */
				$acl_group_contact = civicrm_api( 'GroupContact', 'Delete', $groupParams );

			}

		}

	}



	/**
	 * Update a BP group when a CiviCRM contact is added to a group.
	 *
	 * @since 0.1
	 *
	 * @param string $op The type of database operation
	 * @param string $object_name The type of object
	 * @param integer $civi_group_id The ID of the CiviCRM group
	 * @param array $contact_ids Array of CiviCRM Contact IDs
	 */
	public function group_contacts_added( $op, $object_name, $civi_group_id, $contact_ids ) {

		// target our operation
		if ( $op != 'create' ) return;

		// target our object type
		if ( $object_name != 'GroupContact' ) return;

		// get group data
		$civi_group = $this->get_civi_group_by_id( $civi_group_id );

		// sanity check
		if ( $civi_group === false ) return;

		// get BP group ID
		$bp_group_id = $this->get_bp_group_id_by_civi_group( $civi_group );

		// sanity check
		if ( $bp_group_id === false ) return;

		// loop through contacts
		if ( count( $contact_ids ) > 0 ) {
			foreach( $contact_ids AS $contact_id ) {

				// get contact data
				$contact = $this->get_contact_by_contact_id( $contact_id );

				// sanity check and add if okay
				if ( $contact !== false ) $contacts[] = $contact;

			}
		}

		// assume member group
		$is_admin = 0;

		// is this an ACL group?
		if ( $this->is_acl_group( $civi_group ) ) {

			// add as admin
			$is_admin = 1;

		}

		// add contacts to BP group
		$this->bp->create_group_members( $bp_group_id, $contacts, $is_admin );

		// if it was an ACL group they were added to, we also need to add them to
		// the member group - so, bail if this is a member group
		if ( $is_admin == 0 ) return;

		// first, remove this action, otherwise we'll recurse
		remove_action( 'civicrm_pre', array( $this, 'group_contacts_added' ), 10 );

		// get the CiviCRM group ID of the member group
		$civi_group_id = $this->find_group_id(
			$this->member_group_get_sync_name( $bp_group_id )
		);

		// sanity check
		if ( $civi_group_id ) {

			// loop through contacts
			foreach( $contacts AS $contact ) {

				// add to member group
				$this->group_contact_create( $civi_group_id, $contact['contact_id'] );

			}

		}

		// re-add this action
		add_action( 'civicrm_pre', array( $this, 'group_contacts_added' ), 10, 4 );

	}



	/**
	 * Update a BP group when a CiviCRM contact is deleted (or removed) from a group.
	 *
	 * @since 0.1
	 *
	 * @param string $op The type of database operation
	 * @param string $object_name The type of object
	 * @param integer $civi_group_id The ID of the CiviCRM group
	 * @param array $contact_ids Array of CiviCRM Contact IDs
	 */
	public function group_contacts_deleted( $op, $object_name, $civi_group_id, $contact_ids ) {

		// target our operation
		if ( $op != 'delete' ) return;

		// target our object type
		if ( $object_name != 'GroupContact' ) return;

		// get group data
		$civi_group = $this->get_civi_group_by_id( $civi_group_id );

		// sanity check
		if ( $civi_group === false ) return;

		// get BP group ID
		$bp_group_id = $this->get_bp_group_id_by_civi_group( $civi_group );

		// sanity check
		if ( $bp_group_id === false ) return;

		// loop through contacts
		if ( count( $contact_ids ) > 0 ) {
			foreach( $contact_ids AS $contact_id ) {

				// get contact data
				$contact = $this->get_contact_by_contact_id( $contact_id );

				// sanity check and add if okay
				if ( $contact !== false ) $contacts[] = $contact;

			}
		}

		// is this an ACL group?
		if ( $this->is_acl_group( $civi_group ) ) {

			// demote to member of BP group
			$this->bp->demote_group_members( $bp_group_id, $contacts );

			// skip deletion
			return;

		}

		// delete from BP group
		$this->bp->delete_group_members( $bp_group_id, $contacts );

		// first, remove this action, otherwise we'll recurse
		remove_action( 'civicrm_pre', array( $this, 'group_contacts_deleted' ), 10 );

		// get the group ID of the acl group
		$civi_group_id = $this->find_group_id(
			$this->acl_group_get_sync_name( $bp_group_id )
		);

		// sanity check
		if ( $civi_group_id ) {

			// loop through contacts
			foreach( $contacts AS $contact ) {

				// remove member from group
				$this->group_contact_delete( $civi_group_id, $contact['contact_id'] );

			}

		}

		// re-add this action
		add_action( 'civicrm_pre', array( $this, 'group_contacts_deleted' ), 10, 4 );

	}



	/**
	 * Update a BP group when a CiviCRM contact is re-added to a group.
	 *
	 * The issue here is that CiviCRM fires 'civicrm_pre' with $op = 'delete' regardless
	 * of whether the contact is being removed or deleted. If a contact is later re-added
	 * to the group, then $op != 'create', so we need to intercept $op = 'edit'.
	 *
	 * @since 0.1
	 *
	 * @param string $op The type of database operation
	 * @param string $object_name The type of object
	 * @param integer $civi_group_id The ID of the CiviCRM group
	 * @param array $contact_ids Array of CiviCRM Contact IDs
	 */
	public function group_contacts_rejoined( $op, $object_name, $civi_group_id, $contact_ids ) {

		// target our operation
		if ( $op != 'edit' ) return;

		// target our object type
		if ( $object_name != 'GroupContact' ) return;

		// first, remove this action, in case we recurse
		remove_action( 'civicrm_pre', array( $this, 'group_contacts_rejoined' ), 10 );

		// set op to 'create'
		$op = 'create';

		// use our group contact addition callback
		$this->group_contacts_added( $op, $object_name, $civi_group_id, $contact_ids );

		// re-add this action
		add_action( 'civicrm_pre', array( $this, 'group_contacts_rejoined' ), 10, 4 );

	}



	/**
	 * Get CiviCRM contact ID by WordPress user ID.
	 *
	 * @since 0.1
	 *
	 * @param int $user_id The numeric ID of the WordPress user
	 * @return int $civi_contact_id The numeric ID of the CiviCRM Contact
	 */
	public function get_contact_id( $user_id ) {

		// init or die
		if ( ! $this->is_active() ) return;

		// make sure CiviCRM file is included
		require_once 'CRM/Core/BAO/UFMatch.php';

		// do initial search
		$civi_contact_id = CRM_Core_BAO_UFMatch::getContactId( $user_id );
		if ( $civi_contact_id ) {
			return $civi_contact_id;
		}

		// else synchronize contact for this user
		$user = get_userdata( $user_id );
		if ( $user ) {

			// sync this user
			CRM_Core_BAO_UFMatch::synchronizeUFMatch(
				$user, // user object
				$user->ID, // ID
				$user->user_email, // unique identifier
				'WordPress', // CMS
				null, // status
				'Individual', // contact type
				null // is_login
			);

			// get the CiviCRM contact ID
			$civi_contact_id = CRM_Core_BAO_UFMatch::getContactId( $user_id );
			if ( ! $civi_contact_id ) {

				// what to do?
				if ( BP_GROUPS_CIVICRM_SYNC_DEBUG ) {

					// construct verbose error string
					$error = sprintf(
						__( 'No CiviCRM Contact ID could be found for WordPress User ID %d', 'bp-groups-civicrm-sync' ),
						$user_id
					);

					// log something
					error_log( print_r( array(
						'method' => __METHOD__,
						'error' => $error,
						'user' => print_r( new WP_User( $user_id ), true ),
						'backtrace' => wp_debug_backtrace_summary(),
					), true ) );

				}

				// fallback
				return false;

			}

		}

		// --<
		return $civi_contact_id;
	}



	//##########################################################################



	/**
	 * Get CiviCRM contact data by contact ID.
	 *
	 * @since 0.1
	 *
	 * @param int $contact_id The numeric ID of the CiviCRM contact
	 * @return mixed $civi_contact The array of data for the CiviCRM Contact, or false if not found
	 */
	public function get_contact_by_contact_id( $contact_id ) {

		// get all contact data
		$params = array(
			'version' => 3,
			'contact_id' => $contact_id,
		);

		// use API
		$contact_data = civicrm_api( 'contact', 'get', $params );

		// bail if we get any errors
		if ( $contact_data['is_error'] == 1 ) return false;
		if ( ! isset( $contact_data['values'] ) ) return false;
		if ( count( $contact_data['values'] ) === 0 ) return false;

		// get contact
		$contact = array_shift( $contact_data['values'] );

		// --<
		return $contact;

	}



	//##########################################################################



	/**
	 * Find a CiviCRM group ID by source and (optionally) by title.
	 *
	 * @since 0.1
	 *
	 * @param string $source The sync name for the CiviCRM Group
	 * @param string $title The title of the CiviCRM Group to search for
	 * @param bool $abort Whether to die on failure or not
	 * @return int|bool $group_id The ID of the CiviCRM group
	 */
	public function find_group_id( $source, $title = null, $abort = false ) {

		// construct query
		$query = "SELECT id FROM civicrm_group WHERE source = %1";

		// define replacement
		$params = array( 1 => array( $source, 'String' ) );

		// add title search, if present
		if ( $title ) {

			// add to query
			$query .= " OR title = %2";

			// add to replacements
			$params[2] = array( $title, 'String' );

		}

		// let CiviCRM get the group ID
		$civi_group_id = CRM_Core_DAO::singleValueQuery( $query, $params );

		// check for failure and our flag
		if ( $abort AND ! $civi_group_id ) {

			// construct meaningful error
			$error = sprintf(
				__( 'No CiviCRM Group ID could be found for %s', 'bp-groups-civicrm-sync' ),
				$source
			);

			// get the group object
			$group = groups_get_group( array( 'group_id' => $group->id, 'populate_extras' => true ) );

			// log something
			error_log( print_r( array(
				'method' => __METHOD__,
				'error' => $error,
				'group' => print_r( $group, true ),
				'backtrace' => wp_debug_backtrace_summary(),
			), true ) );

			// harsh, but it's the CiviCRM way
			die();

		}

		// --<
		return $civi_group_id;

	}



	/**
	 * Get CiviCRM group data by CiviCRM group ID.
	 *
	 * @since 0.1
	 *
	 * @param int $civi_group_id The numeric ID of the CiviCRM group
	 * @return mixed $group The array of CiviCRM group data, or false if none found
	 */
	public function get_civi_group_by_id( $civi_group_id ) {

		// define get "all with no parent" params
		$params = array(
			'version' => 3,
			'group_id' => $civi_group_id,
		);

		// get group
		$civi_group = civicrm_api( 'group', 'get', $params );

		// bail if we get any errors
		if ( $civi_group['is_error'] == 1 ) return false;
		if ( ! isset( $civi_group['values'] ) ) return false;
		if ( count( $civi_group['values'] ) === 0 ) return false;

		// get group data
		$group = array_shift( $civi_group['values'] );

		// --<
		return $group;

	}



	/**
	 * Get a BuddyPress group ID by CiviCRM Group data.
	 *
	 * @since 0.1
	 *
	 * @param array $civi_group The array of CiviCRM group data
	 * @return mixed $bp_group_id The numeric ID of the BP group, or false if none found
	 */
	public function get_bp_group_id_by_civi_group( $civi_group ) {

		// bail if group has no reference to BP
		if ( ! $this->has_bp_group( $civi_group ) ) return false;

		// get BP group ID - source is of the form BP Sync Group :BPID:
		$tmp = explode( ':', $civi_group['source'] );
		$bp_group_id = $tmp[1];

		// --<
		return $bp_group_id;

	}



	/**
	 * Get the type of CiviCRM Group by "source" string.
	 *
	 * @since 0.1
	 *
	 * @param string $source The source name of the CiviCRM Group
	 * @return array $group_type The type of CiviCRM Group (either 'member' or 'acl')
	 */
	public function civi_group_get_code_by_source( $source ) {

		// init return
		$group_type = false;

		// check for member group
		if ( strstr( $source, 'BP Sync Group :' ) !== false ) {

			// set group type flag
			$group_type = 'member';

		}

		// check for ACL group
		if ( strstr( $source, 'BP Sync Group ACL :' ) !== false ) {

			// set group type flag
			$group_type = 'acl';

		}

		// --<
		return $group_type;

	}



	/**
	 * Get the type of CiviCRM Group by type string.
	 *
	 * @since 0.1
	 *
	 * @param string $group_type The type of CiviCRM Group (either 'member' or 'acl')
	 * @return array $return Associative array of CiviCRM group types for the API
	 */
	public function civi_group_get_type_by_code( $group_type ) {

		// if 'member'
		if ( $group_type == 'member' ) {

			// define CiviCRM group type (Mailing List by default)
			$type_data = apply_filters( 'bp_groups_civicrm_sync_member_group_type', array( '2' => 2 ) );

		}

		// if 'acl'
		if ( $group_type == 'acl' ) {

			// define CiviCRM group type (Mailing List by default)
			$type_data = apply_filters( 'bp_groups_civicrm_sync_acl_group_type', array( '1' => 1 ) );

		}

		// --<
		return $type_data;

	}



	/**
	 * Check if a CiviCRM Group has an associated BuddyPress group.
	 *
	 * @since 0.1
	 *
	 * @param array $civi_group The array of CiviCRM group data
	 * @return boolean $has_group True if CiviCRM group has a BP group, false if not
	 */
	public function has_bp_group( $civi_group ) {

		// if the group source has no reference to BP, then it's not
		if ( ! isset( $civi_group['source'] ) ) return false;
		if ( strstr( $civi_group['source'], 'BP Sync Group' ) === false ) return false;

		// --<
		return true;

	}



	/**
	 * Construct name for CiviCRM group.
	 *
	 * @since 0.1
	 *
	 * @param int $bp_group_id The BuddyPress group ID
	 * @return string
	 */
	public function member_group_get_sync_name( $bp_group_id ) {

		// construct name, based on OG schema
		return 'BP Sync Group :' . $bp_group_id . ':';

	}



	/**
	 * Check if a CiviCRM Group is a BuddyPress member group.
	 *
	 * @since 0.1
	 *
	 * @param array $civi_group The array of CiviCRM group data
	 * @return boolean $is_member_group True if CiviCRM group is a BP member group, false if not
	 */
	public function is_member_group( $civi_group ) {

		// bail if group has no reference to BP
		if ( ! $this->has_bp_group( $civi_group ) ) return false;

		// if the group source has a reference to BP, then it is
		if ( strstr( $civi_group['source'], 'BP Sync Group :' ) !== false ) return true;

		// --<
		return false;

	}



	/**
	 * Construct name for CiviCRM ACL group.
	 *
	 * @since 0.1
	 *
	 * @param int $bp_group_id The BuddyPress group ID
	 * @return string
	 */
	public function acl_group_get_sync_name( $bp_group_id ) {

		// construct name, based on OG schema
		return 'BP Sync Group ACL :' . $bp_group_id . ':';

	}



	/**
	 * Check if a CiviCRM Group is a BuddyPress ACL group.
	 *
	 * @since 0.1
	 *
	 * @param array $civi_group The array of CiviCRM group data
	 * @return boolean $is_acl_group True if CiviCRM group is a BP ACL group, false if not
	 */
	public function is_acl_group( $civi_group ) {

		// bail if group has no reference to BP
		if ( ! $this->has_bp_group( $civi_group ) ) return false;

		// if the group source has a reference to BP ACL, then it is
		if ( strstr( $civi_group['source'], 'BP Sync Group ACL :' ) !== false ) return true;

		// --<
		return false;

	}



	//##########################################################################



	/**
	 * Update ACL tables.
	 *
	 * @since 0.1
	 *
	 * @param array $aclParams The array of CiviCRM API params
	 * @param string $op The CiviCRM database operation
	 */
	public function updateCiviACLTables( $aclParams, $op ) {

		// Drupal-esque operation
		if ( $op == 'delete' ) {
			$this->updateCiviACL( $aclParams, $op );
			$this->updateCiviACLEntityRole( $aclParams, $op );
			$this->updateCiviACLRole( $aclParams, $op );
		} else {
			$this->updateCiviACLRole( $aclParams, $op );
			$this->updateCiviACLEntityRole( $aclParams, $op );
			$this->updateCiviACL( $aclParams, $op );
		}
	}



	/**
	 * Update ACL role.
	 *
	 * @since 0.1
	 *
	 * @param array $params The array of CiviCRM API params
	 * @param string $op The CiviCRM database operation
	 */
	public function updateCiviACLRole( &$params, $op ) {

		$optionGroupID = CRM_Core_DAO::getFieldValue(
			'CRM_Core_DAO_OptionGroup',
			'acl_role',
			'id',
			'name'
		);

		$dao = new CRM_Core_DAO_OptionValue();
		$dao->option_group_id = $optionGroupID;
		$dao->description = $params['source'];

		if ( $op == 'delete' ) {
			$dao->delete();
			return;
		}

		$dao->label = $params['title'];
		$dao->is_active = 1;

		$weightParams = array('option_group_id' => $optionGroupID);
		$dao->weight = CRM_Utils_Weight::getDefaultWeight(
			'CRM_Core_DAO_OptionValue',
			$weightParams
		);

		$dao->value = CRM_Utils_Weight::getDefaultWeight(
			'CRM_Core_DAO_OptionValue',
			$weightParams,
			'value'
		);

		$query = "
			SELECT v.id
			FROM civicrm_option_value v
			WHERE v.option_group_id = %1
			AND v.description     = %2
		";

		$queryParams = array(
			1 => array($optionGroupID, 'Integer'),
			2 => array($params['source'], 'String'),
		);

		$dao->id = CRM_Core_DAO::singleValueQuery( $query, $queryParams );

		$dao->save();
		$params['acl_role_id'] = $dao->value;

	}



	/**
	 * Update ACL entity role.
	 *
	 * @since 0.1
	 *
	 * @param array $params The array of CiviCRM API params
	 * @param string $op The CiviCRM database operation
	 */
	public function updateCiviACLEntityRole( &$params, $op ) {

		$dao = new CRM_ACL_DAO_EntityRole();

		$dao->entity_table = 'civicrm_group';
		$dao->entity_id = $params['acl_group_id'];

		if ( $op == 'delete' ) {
			$dao->delete();
			return;
		}

		$dao->acl_role_id = $params['acl_role_id'];

		$dao->find(TRUE);
		$dao->is_active = TRUE;
		$dao->save();
		$params['acl_entity_role_id'] = $dao->id;

	}



	/**
	 * Update ACL.
	 *
	 * @since 0.1
	 *
	 * @param array $params The array of CiviCRM API params
	 * @param string $op The CiviCRM database operation
	 */
	public function updateCiviACL( &$params, $op ) {

		$dao = new CRM_ACL_DAO_ACL();

		$dao->object_table = 'civicrm_saved_search';
		$dao->object_id = $params['civicrm_group_id'];

		if ( $op == 'delete' ) {
			$dao->delete();
			return;
		}

		$dao->find(TRUE);

		$dao->entity_table = 'civicrm_acl_role';
		$dao->entity_id    = $params['acl_role_id'];
		$dao->operation    = 'Edit';

		$dao->is_active = TRUE;
		$dao->save();
		$params['acl_id'] = $dao->id;

	}



	//##########################################################################



	/**
	 * Enable a BP group to be created when creating a CiviCRM group.
	 *
	 * @since 0.1
	 *
	 * @param string $formName The CiviCRM form name
	 * @param object $form The CiviCRM form object
	 */
	public function form_create_bp_group_options( $formName, &$form ) {

		// is this the group edit form?
		if ( $formName != 'CRM_Group_Form_Edit' ) return;

		// get CiviCRM group
		$civi_group = $form->getVar( '_group' );

		// if we have a group, bail
		if ( isset( $civi_group ) ) return;
		if ( ! empty( $civi_group ) ) return;

		// okay, it's the new group form

		// Add the field element in the form
		$form->add( 'checkbox', 'bpgroupscivicrmsynccreatefromnew', __( 'Create BuddyPress Group', 'bp-groups-civicrm-sync' ) );

		// dynamically insert a template block in the page
		CRM_Core_Region::instance('page-body')->add( array(
			'template' => 'bp-groups-civicrm-sync-new.tpl'
		));

	}



	/**
	 * Create a BP group when creating a CiviCRM group.
	 *
	 * @since 0.1
	 *
	 * @param string $formName The CiviCRM form name
	 * @param object $form The CiviCRM form object
	 */
	public function form_create_bp_group_process( $formName, &$form ) {

		// kick out if not group edit form
		if ( ! ( $form instanceof CRM_Group_Form_Edit ) ) return;

		// inspect submitted values
		$values = $form->getVar( '_submitValues' );

		// was our checkbox ticked?
		if ( ! isset( $values['bpgroupscivicrmsynccreatefromnew'] ) ) return;
		if ( $values['bpgroupscivicrmsynccreatefromnew'] != '1' ) return;

		// the group hasn't been created yet

		// get CiviCRM group
		$civi_group = $form->getVar( '_group' );

		// convert to BP group
		$this->civi_group_to_bp_group_convert( $civi_group );

	}



	/**
	 * Create a BP group from a CiviCRM group.
	 *
	 * @since 0.1
	 *
	 * @param object $civi_group The CiviCRM group object
	 */
	public function civi_group_to_bp_group_convert( $civi_group ) {

		// set flag so that we don't act on the 'groups_create_group' action
		$this->do_not_sync = true;

		// remove hooks
		remove_action( 'groups_create_group', array( $this->bp, 'create_civi_group' ), 100 );
		remove_action( 'groups_details_updated', array( $this->bp, 'update_civi_group_details' ), 100 );

		// create the BuddyPress group
		$bp_group_id = $this->bp->create_group( $civi_group->title, $civi_group->description );

		// re-add hooks
		add_action( 'groups_create_group', array( $this->bp, 'create_civi_group' ), 100, 3 );
		add_action( 'groups_details_updated', array( $this->bp, 'update_civi_group_details' ), 100, 1 );

		// get all contacts in this group
		$params = array(
			'version' => 3,
			'group' => $civi_group->id,
		);

		// use API to get members
		$group_admins = civicrm_api( 'contact', 'get', $params );

		// do we have any members?
		if ( isset( $group_admins['values'] ) AND count( $group_admins['values'] ) > 0 ) {

			// make admins
			$is_admin = 1;

			// create memberships from the CiviCRM contacts
			$this->bp->create_group_members( $bp_group_id, $group_admins['values'], $is_admin );

		}



		// get source safely
		$source = isset( $civi_group->source ) ? $civi_group->source : '';

		// get the non-ACL CiviCRM group ID
		$civi_group_id = $this->find_group_id(
			str_replace( 'OG Sync Group ACL', 'OG Sync Group', $source )
		);

		// get all contacts in this group
		$params = array(
			'version' => 3,
			'group' => $civi_group_id,
		);

		// use API to get members
		$group_members = civicrm_api( 'contact', 'get', $params );

		// do we have any members?
		if ( isset( $group_members['values'] ) AND count( $group_members['values'] ) > 0 ) {

			// make members
			$is_admin = 0;

			// create memberships from the CiviCRM contacts
			$this->bp->create_group_members( $bp_group_id, $group_members['values'], $is_admin );

		}



		// update the "source" field for both CiviCRM groups

		// define CiviCRM ACL group
		$acl_group_params = array(
			'version' => 3,
			'id' => $civi_group->id,
		);

		// get name for the CiviCRM group
		$acl_group_params['source'] = $this->acl_group_get_sync_name( $bp_group_id );

		// use CiviCRM API to create the group (will update if ID is set)
		$acl_group = civicrm_api( 'group', 'create', $acl_group_params );

		// error check
		if ( $acl_group['is_error'] == '1' ) {

			// debug
			error_log( print_r( array(
				'method' => __METHOD__,
				'acl_group' => $acl_group,
				'backtrace' => wp_debug_backtrace_summary(),
			), true ) );

			// bail
			return;

		}



		// define CiviCRM group
		$member_group_params = array(
			'version' => 3,
			'id' => $civi_group_id,
		);

		// get name for the CiviCRM group
		$member_group_params['source'] = $this->member_group_get_sync_name( $bp_group_id );

		// use CiviCRM API to create the group (will update if ID is set)
		$member_group = civicrm_api( 'group', 'create', $member_group_params );

		// error check
		if ( $member_group['is_error'] == '1' ) {

			// debug
			error_log( print_r( array(
				'method' => __METHOD__,
				'member_group' => $member_group,
				'backtrace' => wp_debug_backtrace_summary(),
			), true ) );

			// bail
			return;

		}



		// if no parent
		if ( isset( $civi_group['parents'] ) AND $civi_group['parents'] == '' ) {

			// assign both to meta group
			$this->group_nesting_update( $bp_group_id, '0' );

		}

	}



	//##########################################################################



	/**
	 * Enable a BP group to be created from pre-existing Drupal OG groups in CiviCRM.
	 *
	 * @since 0.1
	 *
	 * @param string $formName The CiviCRM form name
	 * @param object $form The CiviCRM form object
	 */
	public function form_edit_og_options( $formName, &$form ) {

		// is this the group edit form?
		if ( $formName != 'CRM_Group_Form_Edit' ) return;

		// get CiviCRM group
		$civi_group = $form->getVar( '_group' );

		// get source safely
		$source = isset( $civi_group->source ) ? $civi_group->source : '';

		// in Drupal, OG groups are synced with 'OG Sync Group :GID:'
		// related OG ACL groups are synced with 'OG Sync Group ACL :GID:'

		// is this an OG administrator group?
		if ( strstr( $source, 'OG Sync Group ACL' ) === false ) return;

		// Add the field element in the form
		$form->add( 'checkbox', 'bpgroupscivicrmsynccreatefromog', __( 'Create BuddyPress Group', 'bp-groups-civicrm-sync' ) );

		// dynamically insert a template block in the page
		CRM_Core_Region::instance('page-body')->add( array(
			'template' => 'bp-groups-civicrm-sync-og.tpl'
		));

	}



	/**
	 * Create a BP group based on pre-existing CiviCRM/Drupal/OG group.
	 *
	 * @since 0.1
	 *
	 * @param string $formName The CiviCRM form name
	 * @param object $form The CiviCRM form object
	 */
	public function form_edit_og_process( $formName, &$form ) {

		// kick out if not group edit form
		if ( ! ( $form instanceof CRM_Group_Form_Edit ) ) return;

		// inspect submitted values
		$values = $form->getVar( '_submitValues' );

		// was our checkbox ticked?
		if ( ! isset( $values['bpgroupscivicrmsynccreatefromog'] ) ) return;
		if ( $values['bpgroupscivicrmsynccreatefromog'] != '1' ) return;

		// get CiviCRM group
		$civi_group = $form->getVar( '_group' );

		// convert to BP group
		$this->og_group_to_bp_group_convert( $civi_group );

	}



	/**
	 * Convert all legacy OG CiviCRM Groups to BP CiviCRM Groups.
	 *
	 * @since 0.1
	 */
	public function og_groups_to_bp_groups_convert() {

		// init or die
		if ( ! $this->is_active() ) return;

		// define get all groups params
		$params = array(

			// v3, of course
			'version' => 3,

			// define stupidly high limit, because API defaults to 25
			'options' => array(
				'limit' => '10000',
			),

		);

		// get all groups with no parent ID (get ALL for now)
		$all_groups = civicrm_api( 'group', 'get', $params );

		// if we got some
		if (
			$all_groups['is_error'] == 0 AND
			isset( $all_groups['values'] ) AND
			count( $all_groups['values'] ) > 0
		) {

			// loop through them
			foreach( $all_groups['values'] AS $civi_group ) {

				// send for processing
				$this->og_group_to_bp_group_convert( (object)$civi_group );

			}

		}

		// assign to meta group
		$this->meta_group_groups_assign();

	}



	/**
	 * Create a BP group based on pre-existing CiviCRM/Drupal/OG group.
	 *
	 * @since 0.1
	 *
	 * @param object $civi_group The CiviCRM group object
	 */
	public function og_group_to_bp_group_convert( $civi_group ) {

		// get source
		$source = $civi_group->source;

		// in Drupal, OG groups are synced with 'OG Sync Group :GID:'
		// related OG ACL groups are synced with 'OG Sync Group ACL :GID:'

		// is this an OG administrator group?
		if ( strstr( $source, 'OG Sync Group ACL' ) === false ) return;

		// set flag so that we don't act on the 'groups_create_group' action
		$this->do_not_sync = true;

		// remove hooks
		remove_action( 'groups_create_group', array( $this->bp, 'create_civi_group' ), 100 );
		remove_action( 'groups_details_updated', array( $this->bp, 'update_civi_group_details' ), 100 );

		// sanitise title by stripping suffix
		$bp_group_title = array_shift( explode( ': Administrator', $civi_group->title ) );

		// create the BuddyPress group
		$bp_group_id = $this->bp->create_group( $bp_group_title, $civi_group->description );

		// re-add hooks
		add_action( 'groups_create_group', array( $this->bp, 'create_civi_group' ), 100, 3 );
		add_action( 'groups_details_updated', array( $this->bp, 'update_civi_group_details' ), 100, 1 );



		// get all contacts in this group
		$params = array(
			'version' => 3,
			'group' => $civi_group->id,
		);

		// use API to get members
		$group_admins = civicrm_api( 'contact', 'get', $params );

		// do we have any members?
		if ( isset( $group_admins['values'] ) AND count( $group_admins['values'] ) > 0 ) {

			// make admins
			$is_admin = 1;

			// create memberships from the CiviCRM contacts
			$this->bp->create_group_members( $bp_group_id, $group_admins['values'], $is_admin );

		}



		// get the non-ACL CiviCRM group ID
		$civi_group_id = $this->find_group_id(
			str_replace( 'OG Sync Group ACL', 'OG Sync Group', $source )
		);

		// get all contacts in this group
		$params = array(
			'version' => 3,
			'group' => $civi_group_id,
		);

		// use API to get members
		$group_members = civicrm_api( 'contact', 'get', $params );

		// do we have any members?
		if ( isset( $group_members['values'] ) AND count( $group_members['values'] ) > 0 ) {

			// make members
			$is_admin = 0;

			// create memberships from the CiviCRM contacts
			$this->bp->create_group_members( $bp_group_id, $group_members['values'], $is_admin );

		}



		// update the "source" field for both CiviCRM groups

		// define CiviCRM ACL group
		$acl_group_params = array(
			'version' => 3,
			'id' => $civi_group->id,
		);

		// get name for the CiviCRM group
		$acl_group_params['source'] = $this->acl_group_get_sync_name( $bp_group_id );

		// use CiviCRM API to create the group (will update if ID is set)
		$acl_group = civicrm_api( 'group', 'create', $acl_group_params );

		// error check
		if ( $acl_group['is_error'] == '1' ) {

			// debug
			error_log( print_r( array(
				'method' => __METHOD__,
				'acl_group' => $acl_group,
				'backtrace' => wp_debug_backtrace_summary(),
			), true ) );

			// bail
			return;

		}



		// define CiviCRM group
		$member_group_params = array(
			'version' => 3,
			'id' => $civi_group_id,
		);

		// get name for the CiviCRM group
		$member_group_params['source'] = $this->member_group_get_sync_name( $bp_group_id );

		// use CiviCRM API to create the group (will update if ID is set)
		$member_group = civicrm_api( 'group', 'create', $member_group_params );

		// error check
		if ( $member_group['is_error'] == '1' ) {

			// debug
			error_log( print_r( array(
				'method' => __METHOD__,
				'member_group' => $member_group,
				'backtrace' => wp_debug_backtrace_summary(),
			), true ) );

			// bail
			return;

		}



		// if no parent
		if ( isset( $civi_group['parents'] ) AND $civi_group['parents'] == '' ) {

			// assign both to meta group
			$this->group_nesting_update( $bp_group_id, '0' );

		}

	}



	/**
	 * Do we have any legacy OG CiviCRM Groups?
	 *
	 * @since 0.1
	 *
	 * @return bool
	 */
	public function has_og_groups() {

		// init or die
		if ( ! $this->is_active() ) return;

		// define get all groups params
		$params = array(
			'version' => 3,
		);

		// get all groups with no parent ID (get ALL for now)
		$all_groups = civicrm_api( 'group', 'get', $params );

		// if we got some
		if (
			$all_groups['is_error'] == 0 AND
			isset( $all_groups['values'] ) AND
			count( $all_groups['values'] ) > 0
		) {

			// loop through them
			foreach( $all_groups['values'] AS $civi_group ) {

				// if "source" is not present, it's not an OG group
				if ( ! isset( $civi_group['source'] ) OR is_null( $civi_group['source'] ) ) continue;

				// get source
				$source = $civi_group['source'];

				// in Drupal, OG groups are synced with 'OG Sync Group :GID:'
				// related OG ACL groups are synced with 'OG Sync Group ACL :GID:'

				// is this an OG administrator group?
				if ( strstr( $source, 'OG Sync Group ACL :' ) !== false ) return true;

				// is this an OG member group?
				if ( strstr( $source, 'OG Sync Group :' ) !== false ) return true;

			}

		}

		// --<
		return false;

	}



	//##########################################################################



	/**
	 * Updates a CiviCRM Contact when a WordPress user is updated.
	 *
	 * @since 0.1
	 *
	 * @param object $user a WP user object
	 */
	function _civi_contact_update( $user ) {

		// make sure CiviCRM file is included
		require_once 'CRM/Core/BAO/UFMatch.php';

		// synchronizeUFMatch returns the contact object
		$civi_contact = CRM_Core_BAO_UFMatch::synchronizeUFMatch(
			$user, // user object
			$user->ID, // ID
			$user->user_email, // unique identifier
			'WordPress', // CMS
			null, // unused
			'Individual' // contact type
		);

	}



} // class ends
