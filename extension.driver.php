<?php

	Class extension_sitemap_xml extends Extension{
	
		public function about(){
			return array(
				'name' => 'Sitemap XML',
				'version' => '2.0a',
				'release-date' => '2011-06-10',
				'author' => array(
				 		'name' => 'Phill Gray',
						'email' => 'phill@randb.com.au'
					)
		 		);
		}
		
		public function fetchNavigation() {
			return array(
				array(
					'location' => 'Blueprints',
					'name'	=> 'Sitemap XML',
					'link'	=> '/xml/',
				),
			);
		}
		
		public function getSubscribedDelegates() {
			return array(
				array(
					'page' => '/system/preferences/',
					'delegate' => 'AddCustomPreferenceFieldsets',
					'callback' => '__appendPreferences'
				),
				array(
					'page' => '/backend/',
					'delegate' => 'InitaliseAdminPageHead',
					'callback' => 'initaliseAdminPageHead'
				)
			);
		}
		
		public function install() {
			// Add defaults to config.php
			if (!Symphony::Configuration()->get('index_type', 'sitemap_xml')) {
				Symphony::Configuration()->set('index_type', 'index', 'sitemap_xml');
				Symphony::Configuration()->set('global', 'sitemap', 'sitemap_xml');
				Symphony::Configuration()->set('lastmod', date('c', time()), 'sitemap_xml');
				Symphony::Configuration()->set('changefreq', 'monthly', 'sitemap_xml');
			}
			
			// Add table to database 
			Symphony::Database()->query('
				CREATE TABLE IF NOT EXISTS tbl_sitemap_xml (
					`id` INT(4) UNSIGNED DEFAULT NULL AUTO_INCREMENT,
					`page_id` INT(4) UNSIGNED DEFAULT NULL,
					`datasource_handle` VARCHAR(255) DEFAULT NULL,
					`relative_url` TINYTEXT DEFAULT NULL,
					PRIMARY KEY (`id`),
					UNIQUE KEY datasource_handle_page_id (`datasource_handle`, `page_id`)
				) ENGINE=MyISAM
			');
			
			// Autogenerate a blank sitemap.xml
			$fp = fopen(getcwd() . '/sitemap.xml', 'w+');
			fclose($fp);
			
			return Administration::instance()->saveConfig();
		}
		
		public function uninstall() {
			Symphony::Configuration()->remove('sitemap_xml');
			Symphony::Database()->query('DROP TABLE IF EXISTS tbl_sitemap_xml');
			return Administration::instance()->saveConfig();
		}
		
		public function initaliseAdminPageHead($context) {
			$callback = Symphony::Engine()->getPageCallback();
			
			// Append assets
			if($callback['driver'] == 'systempreferences') {
				Symphony::Engine()->Page->addScriptToHead(URL . '/extensions/sitemap_xml/assets/sitemap_xml.ajax.js', 10001);
			}
		}
		
		public function __appendPreferences($context) {
			/*@group Fieldset containing config settings*/
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings');
			$fieldset->appendChild(new XMLElement('legend', __('Sitemap XML')));
			$context['wrapper']->appendChild($fieldset);
			
			/* group 1*/
			$group = new XMLElement('div');
			$group->setAttribute('class', 'group');
			
			$label = Widget::Label(__('Home page type'));
			$label->appendChild(Widget::Input('settings[sitemap_xml][index_type]', General::Sanitize(Symphony::Configuration()->get('index_type', 'sitemap_xml'))));
			$group->appendChild($label);
			
			$label = Widget::Label(__('Global page type'));
			$label->appendChild(Widget::Input('settings[sitemap_xml][global]',General::Sanitize(Symphony::Configuration()->get('global', 'sitemap_xml'))));
			$group->appendChild($label);
			
			$fieldset->appendChild($group);
			
			/* group 2*/
			$group = new XMLElement('div');
			$group->setAttribute('class', 'group');
			
			$label = Widget::Label(__('Modification date of XML'));
			$label->appendChild(Widget::Input('settings[sitemap_xml][lastmod]',General::Sanitize(Symphony::Configuration()->get('lastmod', 'sitemap_xml'))));
			$group->appendChild($label);
			
			$label = Widget::Label(__('Change frequency of XML'));
			$label->appendChild(Widget::Input('settings[sitemap_xml][changefreq]',General::Sanitize(Symphony::Configuration()->get('changefreq', 'sitemap_xml'))));
			$group->appendChild($label);

			$fieldset->appendChild($group);
			/*@group end*/
			
			/*@group Fieldset containing Page Type settings*/
			$group = new XMLElement('fieldset');
			$group->setAttribute('class', 'settings add_pagetype');
			$group->appendChild(new XMLElement('legend', __('Add page type'))); 
			
			$span = new XMLElement('span', NULL, array('class' => 'frame'));
			
			$pages = Symphony::Database()->fetch("SELECT p.* FROM `tbl_pages` AS p ORDER BY p.sortorder ASC");
			
			$page_list = array('');
			foreach($pages as $page) {
				$page_types = Symphony::Database()->fetchCol('type', "SELECT `type` FROM `tbl_pages_types` WHERE page_id = '".$page['id']."' ORDER BY `type` ASC");
				$page['types'] = $page_types;
				
				$parent = null;
				if($page['parent'] != null) {
					$parent = Symphony::Database()->fetch("SELECT p.* FROM `tbl_pages` AS p WHERE p.id =".$page['parent']);
					$parent = $parent[0]['title'].': ';
				}
				
				$page_list[] = array(
					$page['id'], false, $parent.$page['title']
				);
				
				$this->_pages[] = $page;
			}
			
			$label = Widget::Label(__('Pages'));
			$select = Widget::Select('addtype[page][]', $page_list, array('multiple'=>'multiple'));
			$label->appendChild($select);
			$group->appendChild($label);
			
			$label = Widget::Label(__('Type to add to selected pages:'));
			$label->appendChild(Widget::Input('addtype[page_type]', 'high'));
			$group->appendChild($label);
			
			$span->appendChild(new XMLElement('button', __('Add type to pages'), array_merge(array('name' => 'action[add_pagetype]', 'type' => 'submit'))));
	
			$group->appendChild($span);
			$context['wrapper']->appendChild($group);
			/*@group end*/

			/*@group Fieldset containing pinning options*/
			require_once TOOLKIT . '/class.datasourcemanager.php';
			$dsm = new DatasourceManager(Administration::instance());
			$datasources = array('');
			foreach($dsm->listAll() as $ds) {
				$datasources[] = array(
									$ds['handle'], 
									null, 
									$ds['name']
								 );
			}
			
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings pin_to_page');
			$fieldset->appendChild(new XMLElement('legend', __('Pin datasources to page')));
			$context['wrapper']->appendChild($fieldset);
			
			$span = new XMLElement('span', NULL, array('class' => 'frame'));
			
			$group = new XMLElement('div');
			$group->setAttribute('class', 'group');
			
			$label = Widget::Label(__('Datasource:'));
			$label->appendChild(Widget::Select('pin[datasource]', $datasources));
			$group->appendChild($label);
			
			$label = Widget::Label(__('Page'));
			$label->appendChild(Widget::Select('pin[page]', $page_list));
			$group->appendChild($label);
			$fieldset->appendChild($group);
			
			
			$group = new XMLElement('div');
			
			$label = Widget::Label(__('Relative URL'));
			$label->appendChild(Widget::Input('pin[relative_url]', '/'));
			$group->appendChild($label);
			
			$help = new XMLElement('p', 'For example: if the page was News, the relative url might be /{news-title/@handle}/{@id}/. This would output '.URL.'/news/random-article/32/', array('class' => 'help'));
			$group->appendChild($help);
			
			$span->appendChild(new XMLElement('button', __('Pin datasource to page'), array_merge(array('name' => 'action[pin]', 'type' => 'submit'))));
	
			$group->appendChild($span);
			$fieldset->appendChild($group);
			/*@group end*/
			
			
			/*$sitemap_entries = Symphony::Database()->fetch("SELECT * FROM `tbl_sitemap_xml`");
						
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings sitemap_data');
			$fieldset->appendChild(new XMLElement('legend', __('Current alhscvx')));
			$context['wrapper']->appendChild($fieldset);
				
			$table = new XMLElement('table');
			$tableBody = array();
			$tableHead = array(
				array(__('Datasource'), 'col'),
				array(__('Page'), 'col'),
				array(__('Relative URL'), 'col'),
				array(__('Delete'), 'col')
			);	
					
			if(!empty($sitemap_entries)) {
				foreach($sitemap_entries as $entry) {
					$related_page = Symphony::Database()->fetch("SELECT title FROM `tbl_pages` WHERE id=" . $entry['page_id']);
						
					$ds = Widget::TableData(ucfirst(str_replace('_', ' ', $entry['datasource_handle'])));
					$ds->appendChild(Widget::Input("delete[item]", $entry['id'], 'hidden'));
					$page = Widget::TableData($related_page[0]['title']);
					$url = Widget::TableData($entry['relative_url']);
					$button = Widget::TableData(new XMLElement('button', __('Delete'), array_merge(array('name' => 'action[delete]', 'type' => 'submit'))));
						
					$tableBody[] = Widget::TableRow(
						array(
							$ds, 
							$page, 
							$url,
							$button
						)
					);
					
				}
			}
			
			$table = Widget::Table(
				Widget::TableHead($tableHead), 
				Widget::TableBody($tableBody)
			);
			$fieldset->appendChild($table);*/
			
			/*@group mysql query on Type submit*/
			if(isset($_REQUEST['action']['add_pagetype'])){
				$id = $_REQUEST['addtype']['page'];
				$type = $_REQUEST['addtype']['page_type'];
				
				foreach($id as $page) {
					Symphony::Database()->query('
						INSERT INTO tbl_pages_types VALUES ("", "'.$page.'", "'.$type.'")
					');
				}
			}			
			/*@group mysql query on Pin submit*/
			if(isset($_REQUEST['action']['pin'])){
				$page = $_REQUEST['pin']['page'];
				$datasource = $_REQUEST['pin']['datasource'];
				$relative_url = $_REQUEST['pin']['relative_url'];
				
				Symphony::Database()->query('
					INSERT INTO tbl_sitemap_xml VALUES ("", "'.$page.'", "'.$datasource.'", "'.$relative_url.'")
				');
			}
			
			
			
			
			/*@group mysql query on Delete submit*/
			/*if(isset($_REQUEST['action']['delete'])){
				$page = $_REQUEST['delete']['item'];
				
				var_dump($page);
				exit;
				
				Symphony::Database()->query('DELETE FROM tbl_sitemap_xml WHERE page_id=' .$page );
			}*/
		}
	}

?>