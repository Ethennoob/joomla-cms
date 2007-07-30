<?php
/**
 * @version		$Id$
 * @package		Joomla
 * @subpackage	Content
 * @copyright	Copyright (C) 2005 - 2007 Open Source Matters. All rights reserved.
 * @license		GNU/GPL, see LICENSE.php
 * Joomla! is free software. This version may have been modified pursuant to the
 * GNU General Public License, and as distributed it includes or is derivative
 * of works licensed under the GNU General Public License or other free or open
 * source software licenses. See COPYRIGHT.php for copyright notices and
 * details.
 */

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die();

require_once (JPATH_COMPONENT.DS.'view.php');

/**
 * HTML View class for the Content component
 *
 * @static
 * @package		Joomla
 * @subpackage	Content
 * @since 1.5
 */
class ContentViewSection extends ContentView
{
	function display($tpl = null)
	{
		global $mainframe, $option;

		// Initialize some variables
		$user		=& JFactory::getUser();
		$document	=& JFactory::getDocument();

		// Get the menu item object
		$menus = &JMenu::getInstance();
		$menu  = $menus->getActive();

		// Get the page/component configuration
		$params = &$mainframe->getPageParameters('com_content');

		// Request variables
		$limit		= JRequest::getVar('limit', $params->get('display_num'), '', 'int');
		$limitstart	= JRequest::getVar('limitstart', 0, '', 'int');

		//parameters
		$intro		= $params->def('num_intro_articles', 	4);
		$leading	= $params->def('num_leading_articles', 	1);
		$links		= $params->def('num_links', 			4);

		$limit	= $intro + $leading + $links;
		JRequest::setVar('limit', (int) $limit);

		// Get some data from the model
		$items		= & $this->get( 'Data');
		$total		= & $this->get( 'Total');
		$categories	= & $this->get( 'Categories' );
		$section	= & $this->get( 'Section' );

		// Create a user access object for the user
		$access					= new stdClass();
		$access->canEdit		= $user->authorize('com_content', 'edit', 'content', 'all');
		$access->canEditOwn		= $user->authorize('com_content', 'edit', 'content', 'own');
		$access->canPublish		= $user->authorize('com_content', 'publish', 'content', 'all');

		//add alternate feed link
		if($params->get('show_feed_link', 1) == 1)
		{
			$link	= 'index.php?option=com_content&view=section&format=feed&id='.$section->id;
			$attribs = array('type' => 'application/rss+xml', 'title' => 'RSS 2.0');
			$document->addHeadLink(JRoute::_($link.'&type=rss'), 'alternate', 'rel', $attribs);
			$attribs = array('type' => 'application/atom+xml', 'title' => 'Atom 1.0');
			$document->addHeadLink(JRoute::_($link.'&type=atom'), 'alternate', 'rel', $attribs);
		}
		// Set the page title
		if (!empty ($menu->name)) {
			$document->setTitle($menu->name);
		}

		for($i = 0; $i < count($categories); $i++)
		{
			$category =& $categories[$i];
			$category->link = JRoute::_('index.php?view=category&id='.$category->slug);
		}

		$params->def('page_title', $menu->name);

		if ($total == 0) {
			$params->set('show_categories', false);
		}


		jimport('joomla.html.pagination');
		$pagination = new JPagination($total, $limitstart, $limit);

		$this->assign('total',			$total);

		$this->assignRef('items',		$items);
		$this->assignRef('section',		$section);
		$this->assignRef('categories',	$categories);
		$this->assignRef('params',		$params);
		$this->assignRef('user',		$user);
		$this->assignRef('access',		$access);
		$this->assignRef('pagination',	$pagination);

		parent::display($tpl);
	}

	function &getItem( $index = 0, &$params)
	{
		global $mainframe;

		// Initialize some variables
		$user		=& JFactory::getUser();
		$dispatcher	=& JEventDispatcher::getInstance();

		$SiteName	= $mainframe->getCfg('sitename');

		$task		= JRequest::getCmd('task');

		$linkOn		= null;
		$linkText	= null;

		$item =& $this->items[$index];
		$item->text = $item->introtext;

		// Get the page/component configuration and article parameters
		$params	 = clone($params);
		$aparams = new JParameter($item->attribs);

		// Merge article parameters into the page configuration
		$params->merge($aparams);

		// Process the content preparation plugins
		JPluginHelper::importPlugin('content');
		$results = $dispatcher->trigger('onPrepareContent', array (& $item, & $params, 0));

		// Build the link and text of the readmore button
		if (($params->get('show_readmore') && @ $item->readmore) || $params->get('link_titles'))
		{
			// checks if the item is a public or registered/special item
			if ($item->access <= $user->get('aid', 0))
			{
				$linkOn = JRoute::_("index.php?view=article&id=".$item->slug);
				$linkText = JText::_('Read more...');
			}
			else
			{
				$linkOn = JRoute::_("index.php?option=com_user&task=register");
				$linkText = JText::_('Register to read more...');
			}
		}

		$item->readmore_link = $linkOn;
		$item->readmore_text = $linkText;

		$item->event = new stdClass();
		$results = $dispatcher->trigger('onAfterDisplayTitle', array (& $item, & $params,0));
		$item->event->afterDisplayTitle = trim(implode("\n", $results));

		$results = $dispatcher->trigger('onBeforeDisplayContent', array (& $item, & $params, 0));
		$item->event->beforeDisplayContent = trim(implode("\n", $results));

		$results = $dispatcher->trigger('onAfterDisplayContent', array (& $item, & $params, 0));
		$item->event->afterDisplayContent = trim(implode("\n", $results));

		return $item;
	}
}
