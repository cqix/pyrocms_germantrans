<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * @name 		Upgrade Controller
 * @author 		PyroCMS Development Team
 * @package 	PyroCMS
 * @subpackage 	Controllers
 */
class Upgrade extends Controller
{
	private $versions = array('0.9.9.1', '0.9.9.2', '0.9.9.3', '0.9.9.4', '0.9.9.5', '0.9.9.6', '0.9.9.7', '1.0.0');

	function _remap()
	{
		// Always log out first, stops any weirdness with the user system
		$this->ion_auth->logout();

  		$this->load->database();
  		$this->load->dbforge();

		// The version of the db is defined by a 'version' setting
		$db_version = $this->settings->version;

		// What version is the file system running (this is the target version to upgrade to)
  		$file_version = CMS_VERSION;

		// What is the base version of the db, no rc/beta tags.
		list($base_db_version) = explode('-', $db_version);

		if ( ! $db_version)
		{
			show_error('We have no idea what version you are using, which means something has gone seriously wrong. Please contact support@pyrocms.com.');
		}

		// Upgrade is already done
  		if ($db_version == $file_version)
  		{
  			show_error('Looks like the upgrade is already complete, you are already running v'.$db_version.'.');
  		}

		// File version is not supported
  		if ( ! in_array($file_version, $this->versions))
  		{
  			show_error('The upgrade script does not support version '.$file_version.'.');
  		}

		// DB is ahead of files
		else if ( $base_db_version > $file_version )
		{
			show_error('The database is expecting v'.$db_version.' but the version of PyroCMS you are using is v'.$file_version.'. Try downloading a newer version from ' . anchor('http://pyrocms.com/') . '.');
		}

  		while($db_version != $file_version)
  		{
	  		// Find the next version
	  		$pos = array_search($db_version, $this->versions) + 1;
	  		$next_version = isset($this->versions[$pos]) ? $this->versions[$pos] : NULL;

			// next version is not supported
			$next_version or @show_error('The upgrade script does not support version '.$file_version.'.');

  			// Run the method to upgrade that specific version
	  		$function = 'upgrade_' . preg_replace('/[^0-9a-z]/i', '', $next_version);

			// If a method exists and its false fail. no method = no changes
	  		if (method_exists($this, $function) && $this->$function() !== TRUE)
	  		{
	  			show_error('There was an error upgrading to "'.$next_version.'"');
	  		}

	  		$this->settings->set_item('version', $next_version);

			echo "<p><strong>-- Upgraded to " . $next_version . '--</strong></p>';

	  		$db_version = $next_version;
  		}

		echo "<p>The upgrade is complete, please " . anchor('admin', 'click here') . ' to go back to the Control Panel.</p>';
 	}

	function upgrade_100()
	{
		// ---- Upgrade Photos to Galleries -----------------

		$this->load->library('encrypt');

		//create the new galleries tables
		$this->dbforge->drop_table('galleries');
		$this->dbforge->drop_table('gallery_images');

		$galleries_sql = "
			CREATE TABLE `galleries` (
			  `id` int(11) NOT NULL AUTO_INCREMENT,
			  `title` varchar(255) NOT NULL,
			  `slug` varchar(255) NOT NULL,
			  `thumbnail_id` int(11) DEFAULT NULL,
			  `description` text,
			  `parent` int(11) DEFAULT NULL,
			  `updated_on` int(15) NOT NULL,
			  `preview` varchar(255) DEFAULT NULL,
			  `enable_comments` INT( 1 ) DEFAULT NULL,
			  `published` INT(1) DEFAULT NULL,
			  PRIMARY KEY (`id`),
			  UNIQUE KEY `slug` (`slug`),
			  UNIQUE KEY `thumbnail_id` (`thumbnail_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;
		";

		$gallery_images_sql = "
			CREATE TABLE `gallery_images` (
			  `id` int(11) NOT NULL AUTO_INCREMENT,
			  `gallery_id` int(11) NOT NULL,
			  `filename` varchar(255) NOT NULL,
			  `extension` varchar(255) NOT NULL,
			  `title` varchar(255) DEFAULT 'Untitled',
			  `description` text,
			  `uploaded_on` int(15) DEFAULT NULL,
			  `updated_on` int(15) DEFAULT NULL,
			  `order` INT(11) DEFAULT '0',
			  PRIMARY KEY (`id`),
			  KEY `gallery_id` (`gallery_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;
		";

		if($this->db->query($galleries_sql) && $this->db->query($gallery_images_sql))
		{
			$photo_albums = $this->db->get('photo_albums');

			// We have a shiny new galleries table, lets put something in it
			foreach ($photo_albums->result() as $album)
			{
				// prep the galleries info
				$to_insert = array(
					'id'					=> $album->id,
					'title'					=> $album->title,
					'slug'					=> $album->slug,
					'description'			=> $album->description,
					'parent'				=> $album->parent,
					'updated_on'			=> $album->updated_on,
					'enable_comments'		=> $album->enable_comments,
					'published'				=> '1'
				 );

				// Create the gallery record
				if($this->db->insert('galleries', $to_insert))
				{

					//time for the images (woot!)
					$photos = $this->db->get_where('photos', array('album_id' => $album->id));

					foreach ($photos->result() as $photo)
					{
						// prep the image filenames
						$file = explode('.', $photo->filename);

						$filename = $file[0];

						//create the full size image folder
						if(!file_exists('./uploads/galleries/'.$album->slug.'/full'))
						{
							mkdir('./uploads/galleries/'.$album->slug.'/full', 0755, TRUE);
						}
						//copy image to galleries folder
						copy('./application/assets/img/photos/'.$album->id.'/'.$file[0].'.'.$file[1], './uploads/galleries/'.$album->slug.'/full/'.$filename.'.'.$file[1]);

						//create the thumbnail folder
						if(!file_exists('./uploads/galleries/'.$album->slug.'/thumbs'))
						{
							mkdir('./uploads/galleries/'.$album->slug.'/thumbs', 0755, TRUE);
						}
						//copy thumbnail to galleries folder
						copy('./application/assets/img/photos/'.$album->id.'/'.$file[0].'_thumb.'.$file[1], './uploads/galleries/'.$album->slug.'/thumbs/'.$filename.'.'.$file[1]);

						$photo_to_insert = array(
							'id'					=> $photo->id,
							'gallery_id'			=> $photo->album_id,
							'filename'				=> $filename,
							'extension'				=> $file[1],
							'description'			=> $photo->caption,
							'updated_on'			=> $photo->updated_on
						 );

						$this->db->insert('gallery_images', $photo_to_insert);
					}
				}
			}
			//we got this far without erroring out, lets pull the plug on the old data
			$this->dbforge->drop_table('photo_albums');
			$this->dbforge->drop_table('photos');
		}
		// ---- / End Upgrade Photos to Galleries -----------



		// ---- Permissions ---------------------------------

		$this->dbforge->drop_table('permission_roles');
		$this->dbforge->drop_table('permission_rules');

		$this->db->query("
			CREATE TABLE `permissions` (
			  `id` int(11) NOT NULL AUTO_INCREMENT,
			  `group_id` int(11) NOT NULL,
			  `module` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
			  PRIMARY KEY (`id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Contains a list of modules that a group can access.';
		");

		// ---- / End Permissions ---------------------------


		// ---- Modules -------------------------------------

	    $this->dbforge->drop_column('modules', 'controllers');

		echo 'Updated modules table to have an "installed" option.<br/>';
	    $this->dbforge->add_column('modules', array(
	        'installed' => array(
	            'type'        => 'TINYINT',
	            'constraint'  => '1',
	            'null'        => FALSE,
				'default'	  => 0
	        )
	    ));

		// Clear out existing modules
		$this->db->empty_table('modules');

		$this->load->model('modules/module_m');

    	// Loop through directories that hold modules
		$is_core = TRUE;

		foreach (array(APPPATH, ADDONPATH) as $directory)
    	{
    		// Loop through modules
	        foreach(glob($directory.'modules/*', GLOB_ONLYDIR) as $module_name)
	        {
				$slug = basename($module_name);

				echo 'Re-indexing module: <strong>' . $slug .'</strong>.<br/>';

				$this->module_m->install($slug, $is_core);

				$path = $is_core ? APPPATH : ADDONPATH;

				// Before we can install anything we need to know some details about the module
				$details_file = $path . 'modules/' . $slug . '/details'.EXT;

				// Check the details file exists
				if (!is_file($details_file))
				{
					continue;
				}

				// Sweet, include the file
				include_once $details_file;

				// Now call the details class
				$class_name = ucfirst($slug).'_details';

				$details_class = new $class_name;
				
				// Get some basic info
				$module = $details_class->info();

				// Now lets set some details ourselves
				$module['slug'] = $slug;
				$module['version'] = $details_class->version;
				$module['enabled'] = TRUE;
				$module['installed'] = TRUE;
				$module['is_core'] = $is_core;

				// Looks like it installed ok, add a record
				$this->module_m->add($module);
			}

			// Going back around, 2nd time is addons
			$is_core = FALSE;
        }

		// ---- / End Modules --------------------------------


		// ---- Files ----------------------------------------

		echo "Adding file manager tables.<br/>";
		$this->db->query("CREATE TABLE `files` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `folder_id` int(11) NOT NULL DEFAULT '0',
		  `user_id` int(11) NOT NULL DEFAULT '1',
		  `type` enum('a','v','d','i','o') COLLATE utf8_unicode_ci DEFAULT NULL,
		  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
		  `filename` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
		  `description` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
		  `extension` varchar(5) COLLATE utf8_unicode_ci NOT NULL,
		  `mimetype` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
		  `width` int(5) DEFAULT NULL,
		  `height` int(5) DEFAULT NULL,
		  `filesize` int(11) NOT NULL DEFAULT 0,
		  `date_added` int(11) NOT NULL DEFAULT 0,
		  PRIMARY KEY (`id`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");

		$this->db->query("CREATE TABLE `file_folders` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `parent_id` int(11) DEFAULT '0',
		  `slug` varchar(100) NOT NULL,
		  `name` varchar(50) NOT NULL,
		  `date_added` int(11) NOT NULL,
		  PRIMARY KEY (`id`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");

		// ---- / End Files --------------------------------

		// ---- Page Conversion ----------------------------
		echo "Upgrading pages to the new module.<br/>";

		$this->load->library('versioning');
		$this->versioning->set_table('pages');

		// First we need to retrieve the current content from the pages table so no data gets lost
		$pages = $this->db->get('pages');

		// We need to make sure no data gets lost, therefore we're renaming the pages table to pages_old
		$this->dbforge->rename_table('pages', 'pages_old');

	    // We can now recreate the pages table
	    $this->db->query("CREATE TABLE `pages` (
	      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
	      `revision_id` int(11) NOT NULL DEFAULT '0',
	      `slug` varchar(60) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
	      `title` varchar(60) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
	      `parent_id` int(11) DEFAULT '0',
	      `layout_id` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'default',
	      `css` text COLLATE utf8_unicode_ci,
	      `meta_title` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
	      `meta_keywords` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
	      `meta_description` text COLLATE utf8_unicode_ci NOT NULL,
	      `rss_enabled` int(1) NOT NULL DEFAULT '0',
	      `comments_enabled` int(1) NOT NULL DEFAULT '0',
	      `status` enum('draft','live') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'draft',
	      `created_on` int(11) NOT NULL DEFAULT '0',
	      `updated_on` varchar(11) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
	      PRIMARY KEY (`id`),
	      UNIQUE KEY `Unique` (`slug`,`parent_id`),
	      KEY `slug` (`slug`),
	      KEY `parent` (`parent_id`)
	    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='User Editable Pages'
	    ");

	    // Now that the new pages table has been created it's time to create the revisions table
	    $this->db->query("CREATE TABLE `revisions` (
	      `id` int(11) NOT NULL AUTO_INCREMENT,
	      `owner_id` int(11) NOT NULL,
	      `table_name` varchar(100) NOT NULL DEFAULT 'pages',
	      `body` text,
	      `revision_date` int(11) NOT NULL,
	      `author_id` int(11) NOT NULL,
	      PRIMARY KEY (`id`),
	      KEY `Owner ID` (`owner_id`)
	    ) ENGINE=InnoDB DEFAULT CHARSET=utf8
	    ");

	    // So far so good, time to migrate the old data back to the new table
	    foreach ($pages->result() as $page)
	    {
	        // First insert the page without the revision ID to use
	        $to_insert = array(
	            'id'                => $page->id,
	            'slug'                 => $page->slug,
	            'title'                => $page->title,
	            'parent_id'         => $page->parent_id,
	            'layout_id'         => $page->layout_id,
	            'css'                => $page->css,
	            'meta_title'        => $page->meta_title,
	            'meta_keywords'     => $page->meta_keywords,
	            'meta_description'    => $page->meta_description,
	            'rss_enabled'        => $page->rss_enabled,
	            'comments_enabled'    => $page->comments_enabled,
	            'status'            => $page->status,
	            'created_on'        => $page->created_on,
	            'updated_on'        => $page->updated_on,
	         );

	        // Inser the page
	        $this->db->insert('pages', $to_insert);
	        $page_insert_id = $this->db->insert_id();

	        // Create the revsion, retrieve the ID and modify the page we added earlier
	        $revision_id    = $this->versioning->create_revision( array('author_id' => 1, 'owner_id' => $page_insert_id, 'body' => $page->body) );

	        // Now we can modify the pages table so that it uses the correct revision id
	        $this->db->where('id', $page_insert_id);
	        $this->db->update('pages', array('revision_id' => $revision_id) );
	    }

	    // Add the website column to the profiles table
	    $this->dbforge->add_column('profiles', array(
	        'website' => array(
	            'type'        => 'varchar',
	            'constraint'  => '255',
	            'null'        => TRUE
	        )
	    ));

		// Clear some caches
		echo "Clearing the module cache.<br/>";
		$this->cache->delete_all('module_m');
	    
	    return FALSE; // Change this when we go live
	}

	function upgrade_0997()
	{
		echo 'Page titles can have longer names and slugs.<br />';
		$this->db->query("ALTER TABLE `pages` CHANGE `slug` `slug` varchar(255) collate utf8_unicode_ci NOT NULL default ''");
		$this->db->query("ALTER TABLE `pages` CHANGE `title` `title` varchar(255) collate utf8_unicode_ci NOT NULL default ''");

		echo 'Removed default value from pages js field.<br />';
		$this->db->query("ALTER TABLE `pages` CHANGE `js` `js` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL");

		echo 'Added "preview" field to photo_albums table.<br/>';
		$this->dbforge->add_column('photo_albums', array(
			'enable_comments' => array(
				'type' => 'INT',
				'constraint' => 1,
				'default' => 0,
				'null' => FALSE
			)
		));

		return TRUE;
	}

	function upgrade_0996()
	{
		echo 'Disabling XSS cleaning for pages.<br />';
		$this->db->where('slug', 'pages');
		$this->db->update('modules', array('skip_xss' => 1));

		return TRUE;
	}

	function upgrade_0995()
	{
		echo 'Fixed theme_layout in strict mode.<br />';
		$this->dbforge->modify_column('page_layouts', array(
			'theme_layout' => array(
				'name' => 'theme_layout',
				'type' => 'VARCHAR',
				'constraint' => '100',
				'null' => FALSE,
				'default' => ''
			),
		));

		return TRUE;
	}

	function upgrade_0994()
	{
		echo 'Added "preview" field to photo_albums table.<br/>';
		$this->dbforge->add_column('photo_albums', array(
			'preview' => array(
				'type' => 'VARCHAR',
				'constraint' => '255',
				'default' => '',
				'null' => FALSE
			),
		));

		echo 'Fixing broken TinyCIMM record in Permissions list.<br/>';
		$this->db
			->set('name', 'a:4:{s:2:"en";s:8:"TinyCIMM";s:2:"fr";s:8:"TinyCIMM";s:2:"de";s:8:"TinyCIMM";s:2:"pl";s:8:"TinyCIMM";}')
			->where('slug', 'tinycimm')
			->update('modules');

		echo 'Added "js" field to pages table.<br/>';
		$this->dbforge->add_column('pages', array(
			'js' => array(
				'type' => 'TEXT',
				'default' => '',
				'null' => FALSE
			),
		));

		echo 'Clearing page cache.<br/>';
		$this->cache->delete_all('pages_m');

		echo 'Clearing module cache.<br/>';
		$this->cache->delete_all('module_m');

		return TRUE;
	}

	function upgrade_0993()
	{
		$this->db->where('slug', 'dashboard_rss')->update('settings', array('`default`' => 'http://feeds.feedburner.com/pyrocms-installed'));

		echo 'Updated user_id in permission_rules to accept 0 as a value.<br/>';
		$this->db->query('ALTER TABLE permission_rules CHANGE user_id user_id int(11) NOT NULL DEFAULT 0');

		echo 'Adding Twitter token fields to user profiles<br />';
		$this->dbforge->add_column('profiles', array(
			'twitter_access_token' => array(
				'type' => 'VARCHAR',
				'constraint' => '100',
				'null' => TRUE
			),
			'twitter_access_token_secret' => array(
				'type' => 'VARCHAR',
				'constraint' => '100',
				'null' => TRUE
			),
		));

		echo 'Adding twitter consumer key settings<br />';
		$this->db->insert('settings', array('slug' => 'twitter_consumer_key', 'title' => 'Consumer Key', 'description' => 'Twitter Consumer Key.', 'type' => 'text', 'is_required' => 0, 'is_gui' => 1, 'module' => 'twitter'));
		$this->db->insert('settings', array('slug' => 'twitter_consumer_key_secret', 'title' => 'Consumer Key Secret', 'description' => 'Twitter Consumer Key Secret.', 'type' => 'text', 'is_required' => 0, 'is_gui' => 1, 'module' => 'twitter'));

		return TRUE;
	}

	function upgrade_0992()
	{
		echo 'Added missing theme_layout field to page_layouts table.<br />';
		$this->dbforge->add_column('page_layouts', array(
			'theme_layout' => array(
				'type' => 'VARCHAR',
				'constraint' => '100',
				'null' => FALSE
			),
		));
		
		return TRUE;
	}
}