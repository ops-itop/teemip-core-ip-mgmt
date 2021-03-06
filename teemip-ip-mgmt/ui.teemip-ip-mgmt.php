  <?php
// Copyright (C) 2020 TeemIp
//
//   This file is part of TeemIp.
//
//   TeemIp is free software; you can redistribute it and/or modify	
//   it under the terms of the GNU Affero General Public License as published by
//   the Free Software Foundation, either version 3 of the License, or
//   (at your option) any later version.
//
//   TeemIp is distributed in the hope that it will be useful,
//   but WITHOUT ANY WARRANTY; without even the implied warranty of
//   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//   GNU Affero General Public License for more details.
//
//   You should have received a copy of the GNU Affero General Public License
//   along with TeemIp. If not, see <http://www.gnu.org/licenses/>

/**
 * @copyright   Copyright (C) 2020 TeemIp
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

/********************************************************
 * Displays Subnet Block or Subnet tree for a given Org.
 */
function DisplayTree(WebPage $oP, $sOrgId, $sClass)
{
	$bWithSubnet = false;
	if ($sClass == 'IPv4Subnet')
	{
		$sContainerClass = 'IPv4Block';
		$bWithSubnet = true;
	}
	elseif ($sClass == 'IPv6Subnet')
	{
		$sContainerClass = 'IPv6Block';
		$bWithSubnet = true;
	}
	else
	{
		$sContainerClass = $sClass;
	}
	
	$oContainerSet = new CMDBObjectSet(DBObjectSearch::FromOQL("SELECT $sContainerClass AS b WHERE b.org_id = $sOrgId"));
	if (in_array($sContainerClass, array('IPv4Block', 'IPv6Block', 'Domain')))
	{
		// Add delegated blocks, if any
		$oDelegatedContainerSet = new CMDBObjectSet(DBObjectSearch::FromOQL("SELECT $sContainerClass AS b WHERE b.parent_org_id = $sOrgId"));
		$oContainerSet->Append($oDelegatedContainerSet);
	}
	$aTree = array();
	$aNodes = array();
	while($oContainer = $oContainerSet->Fetch())
	{
		$iParentId = $oContainer->Get('parent_id');
		$iKey = $oContainer->GetKey();
		if (!isset($aTree[$iParentId]))
		{
			$aTree[$iParentId] = array();
		}
		$aTree[$iParentId][$iKey] = $oContainer->GetNameForTree();
		$aNodes[$iKey] = $oContainer;
	}
	
	$aParents = array_keys($aTree);
	$aRoots = array();
	foreach($aParents as $id)
	{
		if (!array_key_exists($id, $aNodes))
		{
			$aRoots[] = $id;
		}
	}
	foreach($aRoots as $iRootId)
	{
		DumpNodes($oP, $sOrgId, $iRootId, $aTree, $aNodes, '', $bWithSubnet);
	}
}

/***************************************************
 * Displays nodes of a Subnet Block or Subnet tree.
 */
function DumpNodes($oP, $sOrgId, $iRootId, $aTree, $aNodes, $currValue, $bWithSubnet)
{
	if (array_key_exists($iRootId, $aTree))
	{
		$bMultiple = false;
		
		$aSortedRoots = $aTree[$iRootId];
		asort($aSortedRoots);
		$oP->add("<ul>\n");
		foreach($aSortedRoots as $id => $sName)
		{
			$oP->add("<li>");
			$aNodes[$id]->DisplayAsLeaf($oP, $bWithSubnet, $sOrgId);
			DumpNodes($oP, $sOrgId, $id, $aTree, $aNodes, $currValue, $bWithSubnet);
			$oP->add("</li>\n");
		}
		$oP->add("</ul>\n");
	}
}

/*****************************************************************
 * 
 * Main user interface pages for IP Management module starts here
 *
 * ***************************************************************/
try
{
	if (!defined('__DIR__')) define('__DIR__', dirname(__FILE__));
	if (!defined('APPROOT')) require_once(__DIR__.'/../../approot.inc.php');
	require_once(APPROOT.'/application/application.inc.php');
	require_once(APPROOT.'/application/displayblock.class.inc.php');
	require_once(APPROOT.'/application/itopwebpage.class.inc.php');
	require_once(APPROOT.'/application/loginwebpage.class.inc.php');
	require_once(APPROOT.'/application/startup.inc.php');
	require_once(APPROOT.'/application/wizardhelper.class.inc.php');
	
	$sLoginMessage = LoginWebPage::DoLogin(); // Check user rights and prompt if needed
	$oAppContext = new ApplicationContext();
	
	// Start construction of page

	$oP = new iTopWebPage('');
	$oP->set_base(utils::GetAbsoluteUrlAppRoot().'pages/');
	
	// All the following actions use advanced forms that require more javascript to be loaded
	$oP->add_linked_script("../js/json.js");
	$oP->add_linked_script("../js/forms-json-utils.js");
	$oP->add_linked_script("../js/wizardhelper.js");
	$oP->add_linked_script("../js/wizard.utils.js");
	$oP->add_linked_script("../js/linkswidget.js");
	$oP->add_linked_script("../js/extkeywidget.js");
	
	$oP->add_linked_script(utils::GetAbsoluteUrlModulesRoot()."teemip-ip-mgmt/teemip-ip-mgmt.js");
	
	// Add teemip style sheeet
	$oP->add_linked_stylesheet(utils::GetAbsoluteUrlModulesRoot().'teemip-ip-mgmt/teemip-ip-mgmt.css');
	
	$operation = utils::ReadParam('operation', '');
	switch($operation)
	{
		///////////////////////////////////////////////////////////////////////////////////////////
		
		case 'displaytree':	// Display hierarchical tree for domain, blocks or subnets
			$sClass = utils::ReadParam('class', '', false, 'class');
			// Check if right parameters have been given
			if ( empty($sClass))
			{
				throw new ApplicationException(Dict::Format('UI:Error:1ParametersMissing', 'class'));
			}
			if (($sClass != 'Domain') && ($sClass != 'IPv4Block') && ($sClass != 'IPv6Block') && ($sClass != 'IPv4Subnet') && ($sClass != 'IPv6Subnet'))
			{
				throw new ApplicationException(Dict::Format('UI:Error:WrongActionForClass', $operation, $sClass));
			}

			// Display search bar
			$oSearch = new DBObjectSearch($sClass);
			$aParams = array('open' => true, 'table_id' => '1');
			$oBlock = new DisplayBlock($oSearch, 'search', false /* Asynchronous */, $aParams);
			$oBlock->Display($oP, 1);

			// Set titles
			$sClassLabel = MetaModel::GetName($sClass);
			$oP->set_title(Dict::Format('UI:IPManagement:Action:DisplayTree:'.$sClass.':PageTitle_Class'));
			$oP->add("<p class=\"page-header\">\n");
			$oP->add(MetaModel::GetClassIcon($sClass, true)." ".Dict::Format('UI:IPManagement:Action:DisplayTree:'.$sClass.':Title_Class', $sClassLabel));
			$oP->add("</p>\n");
			
			$oP->add('<div class="display_block">');
			
			// Get number of records
			$iCurrentOrganization = $oAppContext->GetCurrentValue('org_id');
			if ($iCurrentOrganization == '')
			{
				$oSet = new CMDBObjectSet(DBObjectSearch::FromOQL("SELECT $sClass"));
			}
			else
			{
				$oSet = new CMDBObjectSet(DBObjectSearch::FromOQL("SELECT $sClass AS c WHERE c.org_id = $iCurrentOrganization"));
			}
			$sObjectsCount = Dict::Format('UI:Pagination:HeaderNoSelection', $oSet->Count());
			
			// Get actions Menu
			$iListId = $oP->GetUniqueId(); 
			$oMenuBlock = new MenuBlock($oSet->GetFilter(), 'list');
			$sActionsMenu = $oMenuBlock->GetRenderContent($oP, array(), $iListId);
			
			// Get toolkit menu
			// Remove "Add To Dashboard" submenu
			$sHtml = '<div class="itop_popup toolkit_menu" id="tk_'.$iListId.'"><ul><li><img src="../images/toolkit_menu.png"><ul>';
			$aActions = array();	
			utils::GetPopupMenuItems($oP, iPopupMenuExtension::MENU_OBJLIST_TOOLKIT, $oSet, $aActions);
			unset($aActions['UI:Menu:AddToDashboard']);
			unset($aActions['UI:Menu:ShortcutList']);
			$sHtml .= $oP->RenderPopupMenuItems($aActions);
			$sToolkitMenu = $sHtml;
			
			// Display menu line
			$sHtml = "<table style=\"width:100%;\">";
			$sHtml .= "<tr><td class=\"pagination_container\">$sObjectsCount</td><td class=\"menucontainer\">$sToolkitMenu $sActionsMenu</td></tr>";
			$sHtml .= "</table>";
			$oP->Add($sHtml);
			
			// Dump Tree(s)
			$oP->add('<table style="width:100%"><tr><td colspan="2">');
			$oP->add('<div style="vertical-align:top;" id="tree">');
			if ($iCurrentOrganization == '')
			{
				$oSet = new CMDBObjectSet(DBObjectSearch::FromOQL("SELECT Organization"));
				while($oOrg = $oSet->Fetch())
				{
					$oP->add("<h2>".Dict::Format('UI:IPManagement:Action:DisplayTree:'.$sClass.':OrgName', $oOrg->Get('name'))."</h2>\n");				
					DisplayTree ($oP, $oOrg->GetKey(), $sClass);
					$oP->add("<br>");
				}
			}
			else
			{
				$oOrg = MetaModel::GetObject('Organization', $iCurrentOrganization, false /* MustBeFound */);
				$oP->add("<h2>".Dict::Format('UI:IPManagement:Action:DisplayTree:'.$sClass.':OrgName', $oOrg->Get('name'))."</h2>\n");								
				DisplayTree ($oP, $iCurrentOrganization, $sClass);
			}
			$oP->add('</td></tr></table>');
			$oP->add('</div></div>');
			$oP->add_ready_script("\$('#tree ul').treeview();\n");
		break; // End case displaytree
		
		///////////////////////////////////////////////////////////////////////////////////////////
		
		case 'listspace':	// List occupied and unoccupied space within a block
			$sClass = utils::ReadParam('class', '', false, 'class');
			$id = utils::ReadParam('id', '');
			// Check if right parameters have been given
			if ( empty($sClass) || empty($id))
			{
				throw new ApplicationException(Dict::Format('UI:Error:2ParametersMissing', 'class', 'id'));
			}
			if (($sClass != 'IPv4Block') && ($sClass != 'IPv6Block'))
			{
				throw new ApplicationException(Dict::Format('UI:Error:WrongActionForClass', $operation, $sClass));
			}
			
			// Check if the object exists
			$oObj = MetaModel::GetObject($sClass, $id, false /* MustBeFound */);
			if (is_null($oObj))
			{
				$oP->set_title(Dict::S('UI:ErrorPageTitle'));
				$oP->P(Dict::S('UI:ObjectDoesNotExist'));
			}
			else
			{
				// The object can be read - Process request now
				$sClassLabel = MetaModel::GetName($sClass);

				// No search bar (2.5 standard)
				
				// Display action menu
				$oSingletonFilter = new DBObjectSearch($sClass);
				$oSingletonFilter->AddCondition('id', $oObj->GetKey(), '=');
				$oBlock = new MenuBlock($oSingletonFilter, 'details', false);
				$oBlock->Display($oP, -1);
				
				// Set titles
				$oObj->SetPageTitles($oP, 'UI:IPManagement:Action:ListSpace:'.$sClass.':');
				
				// Dump space
				$oP->add('<table style="width:100%"><tr><td colspan="2">');
				$oP->add('<div style="vertical-align:top;" id="tree">');
				$oObj->DisplayAllSpace($oP);
				$oP->add('</td></tr></table>');
				$oP->add('</div></div>'); 
				$oP->add_ready_script("\$('#tree ul').treeview();\n");
			}
		break; // End case listspace
		
		///////////////////////////////////////////////////////////////////////////////////////////
		
		case 'findspace':	// Find space within a block or a subnet
			$sClass = utils::ReadParam('class', '', false, 'class');
			$id = utils::ReadParam('id', '');
			// Check if right parameters have been given
			if ( empty($sClass) || empty($id))
			{
				throw new ApplicationException(Dict::Format('UI:Error:2ParametersMissing', 'class', 'id'));
			}
			if (($sClass != 'IPv4Block') && ($sClass != 'IPv6Block') && ($sClass != 'IPv4Subnet') && ($sClass != 'IPv6Subnet'))
			{
				throw new ApplicationException(Dict::Format('UI:Error:WrongActionForClass', $operation, $sClass));
			}
						
			// Check if the object exists
			$oObj = MetaModel::GetObject($sClass, $id, false /* MustBeFound */);
			if (is_null($oObj))
			{
				$oP->set_title(Dict::S('UI:ErrorPageTitle'));
				$oP->P(Dict::S('UI:ObjectDoesNotExist'));
			}
			else
			{
				// The object can be read - Process request now
				$oObj->DisplayOperationForm($oP, $oAppContext, $operation);
			}
		break; // End case findspace
		
		///////////////////////////////////////////////////////////////////////////////////////////
		
		case 'dofindspace':	// Apply find space action
			$sClass = utils::ReadParam('class', '', false, 'class');
			$id = utils::ReadParam('id', '');
			$sTransactionId = utils::ReadPostedParam('transaction_id', '');
			
			// Check if right parameters have been given
			if ( empty($sClass) || empty($id))
			{
				throw new ApplicationException(Dict::Format('UI:Error:2ParametersMissing', 'class', 'id'));
			}
			if (($sClass != 'IPv4Block') && ($sClass != 'IPv6Block') && ($sClass != 'IPv4Subnet') && ($sClass != 'IPv6Subnet'))
			{
				throw new ApplicationException(Dict::Format('UI:Error:WrongActionForClass', $operation, $sClass));
			}
			
			// Check if the object exists
			$oObj = MetaModel::GetObject($sClass, $id, false /* MustBeFound */);
			if (is_null($oObj))
			{
				$oP->set_title(Dict::S('UI:ErrorPageTitle'));
				$oP->P(Dict::S('UI:ObjectDoesNotExist'));
			}
			else
			{
				// Make sure we don't follow the same path twice in a row.
				$sClassLabel = MetaModel::GetName($sClass);
				if (!utils::IsTransactionValid($sTransactionId, false))
				{
					$oP->set_title(Dict::Format('UI:ModificationPageTitle_Object_Class', $oObj->GetName(), $sClassLabel));
					$oP->p("<strong>".Dict::S('UI:Error:ObjectAlreadyUpdated')."</strong>\n");
				}
				else
				{
					$aPostedParam = $oObj->GetPostedParam($operation);
					
					// Make sure find action can be launched
					$sErrorString = $oObj->DoCheckToDisplayAvailableSpace($aPostedParam);
					if ($sErrorString != '')
					{
						// Found issues, explain and give the user another chance
						$sIssueDesc = Dict::Format('UI:IPManagement:Action:DoFindSpace:'.$sClass.':'.$sErrorString);
						$sMessage = "<div class=\"header_message message_error teemip_message_error\">".$sIssueDesc."</div>";
						$oP->add($sMessage);

						$sNextOperation = $oObj->GetNextOperation($operation);
						$oObj->DisplayOperationForm($oP, $oAppContext, $sNextOperation, $aPostedParam);
					}
					else
					{
						// No search bar (2.5 standard)

						// Display action menu
						$oSingletonFilter = new DBObjectSearch($sClass);
						$oSingletonFilter->AddCondition('id', $oObj->GetKey(), '=');
						$oBlock = new MenuBlock($oSingletonFilter, 'details', false);
						$oBlock->Display($oP, -1);
						
						// Set titles
						$oObj->SetPageTitles($oP, 'UI:IPManagement:Action:DoFindSpace:'.$sClass.':');
						
						// Dump space
						$oP->add('<table style="width:100%"><tr><td colspan="2">');
						$oP->add('<div style="vertical-align:top;" id="tree">');
						$oObj->DoDisplayAvailableSpace($oP, 0, $aPostedParam);
						$oP->add('</div></td></tr></table>');
						$oP->add('</div>');		 // ??
						$oP->add_ready_script("\$('#tree ul').treeview();\n");
						$oP->add("<div id=\"dialog_content\"/>\n");
					}
				}
			}
		break; // End case dofindspace
		
		///////////////////////////////////////////////////////////////////////////////////////////
		
		case 'listips':	// List IPs of a subnet or an IP range
			$sClass = utils::ReadParam('class', '', false, 'class');
			$id = utils::ReadParam('id', '');
			// Check if right parameters have been given
			if ( empty($sClass) || empty($id))
			{
				throw new ApplicationException(Dict::Format('UI:Error:2ParametersMissing', 'class', 'id'));
			}
			if (($sClass != 'IPv4Subnet') && ($sClass != 'IPv6Subnet') && ($sClass != 'IPv4Range') && ($sClass != 'IPv6Range'))
			{
				throw new ApplicationException(Dict::Format('UI:Error:WrongActionForClass', $operation, $sClass));
			}
			
			// Check if the object exists
			$oObj = MetaModel::GetObject($sClass, $id, false /* MustBeFound */);
			if (is_null($oObj))
			{
				$oP->set_title(Dict::S('UI:ErrorPageTitle'));
				$oP->P(Dict::S('UI:ObjectDoesNotExist'));
			}
			else
			{
				// The object can be read - Process request now
				$iSize = $oObj->GetSize();
				if ($iSize >= MAX_NB_OF_IPS_TO_DISPLAY)
				{
					// Display subset of IPs only as size is too big to display all IPs once
					$oObj->DisplayOperationForm($oP, $oAppContext, $operation);
				}
				else
				{
					// Display all IPs once
					$sClassLabel = MetaModel::GetName($sClass);

					// No search bar (2.5 standard)
					
					// Display action menu
					$oSingletonFilter = new DBObjectSearch($sClass);
					$oSingletonFilter->AddCondition('id', $oObj->GetKey(), '=');
					$oBlock = new MenuBlock($oSingletonFilter, 'details', false);
					$oBlock->Display($oP, -1);
					
					// Set titles
					$oObj->SetPageTitles($oP, 'UI:IPManagement:Action:ListIps:'.$sClass.':');
					
					// Dump IP Tree
					$sStatusIp = $oObj->GetDefaultValueAttribute('status');
					$sParameter = array ('first_ip' => '', 'last_ip' => '', 'status_ip' => $sStatusIp, 'short_name' => '', 'domain_id' => '', 'usage_id' => '', 'requestor_id' => '');
					$oP->add('<table style="width:100%"><tr><td colspan="2">');
					$oP->add('<div style="vertical-align:top;" id="tree">');
					$oObj->DoListIps($oP, 0, $sParameter);
					$oP->add('</div></td></tr></table>');
					$oP->add('</div>');		 // ??
					$oP->add_ready_script("\$('#tree ul').treeview();\n");
					$oP->add("<div id=\"dialog_content\"/>\n");
				}
			}
		break; // End case Listips
		
		///////////////////////////////////////////////////////////////////////////////////////////
		
		case 'dolistips':	// Apply list ips action
			$sClass = utils::ReadParam('class', '', false, 'class');
			$id = utils::ReadParam('id', '');
			$sTransactionId = utils::ReadPostedParam('transaction_id', '');
			
			// Check if right parameters have been given
			if ( empty($sClass) || empty($id))
			{
				throw new ApplicationException(Dict::Format('UI:Error:2ParametersMissing', 'class', 'id'));
			}
			if (($sClass != 'IPv4Subnet') && ($sClass != 'IPv6Subnet') && ($sClass != 'IPv4Range') && ($sClass != 'IPv6Range'))
			{
				throw new ApplicationException(Dict::Format('UI:Error:WrongActionForClass', $operation, $sClass));
			}
			
			// Check if the object exists
			$oObj = MetaModel::GetObject($sClass, $id, true /* MustBeFound */);

			// Make sure we don't follow the same path twice in a row.
			$sClassLabel = MetaModel::GetName($sClass);
			if (!utils::IsTransactionValid($sTransactionId, false))
			{
				$oP->set_title(Dict::Format('UI:ModificationPageTitle_Object_Class', $oObj->GetName(), $sClassLabel));
				$oP->p("<strong>".Dict::S('UI:Error:ObjectAlreadyUpdated')."</strong>\n");
			}
			else
			{
				$aPostedParam = $oObj->GetPostedParam($operation);
				
				// Make sure range can be listed
				$sErrorString = $oObj->DoCheckToListIps($aPostedParam);
				if ($sErrorString != '')
				{
					// Found issues, explain and give the user another chance
					$sIssueDesc = Dict::Format('UI:IPManagement:Action:DoListIps:'.$sClass.':CannotBeListed', $sErrorString);
					$sMessage = "<div class=\"header_message message_error teemip_message_error\">".$sIssueDesc."</div>";
					$oP->add($sMessage);
					
					$sNextOperation = $oObj->GetNextOperation($operation);
					$oObj->DisplayOperationForm($oP, $oAppContext, $sNextOperation, $aPostedParam);
					}
				else
				{
					// No search bar (2.5 standard)
					
					// Display action menu
					$oSingletonFilter = new DBObjectSearch($sClass);
					$oSingletonFilter->AddCondition('id', $oObj->GetKey(), '=');
					$oBlock = new MenuBlock($oSingletonFilter, 'details', false);
					$oBlock->Display($oP, -1);
					
					// Set titles
					$oObj->SetPageTitles($oP, 'UI:IPManagement:Action:DoListIps:'.$sClass.':');
					
					// Dump space
					$oP->add('<table style="width:100%"><tr><td colspan="2">');
					$oP->add('<div style="vertical-align:top;" id="tree">');
					$oObj->DoListIps($oP, 0, $aPostedParam);       
					$oP->add('</div></td></tr></table>');
					$oP->add('</div>');		 // ??
					$oP->add_ready_script("\$('#tree ul').treeview();\n");
					$oP->add("<div id=\"dialog_content\"/>\n");
				}
			}
		break; // End case dolistips
		
		///////////////////////////////////////////////////////////////////////////////////////////
		
		case 'shrinkblock':		// Shrink a block
		case 'shrinksubnet':	// Shrink a subnet
			$sClass = utils::ReadParam('class', '', false, 'class');
			$id = utils::ReadParam('id', '');
			// Check if right parameters have been given
			if ( empty($sClass) || empty($id))
			{
				throw new ApplicationException(Dict::Format('UI:Error:2ParametersMissing', 'class', 'id'));
			}
			if (($sClass != 'IPv4Block') && ($sClass != 'IPv6Block') && ($sClass != 'IPv4Subnet'))
			{
				throw new ApplicationException(Dict::Format('UI:Error:WrongActionForClass', $operation, $sClass));
			}
			
			// Check if the object exists
			$oObj = MetaModel::GetObject($sClass, $id, false /* MustBeFound */);
			if (is_null($oObj))
			{
				$oP->set_title(Dict::S('UI:ErrorPageTitle'));
				$oP->P(Dict::S('UI:ObjectDoesNotExist'));
			}
			else
			{
				// The object can be read - Check now that user is allowed to modify it
				$oSet = CMDBObjectSet::FromObject($oObj);
				if (UserRights::IsActionAllowed($sClass, UR_ACTION_MODIFY, $oSet) == UR_ALLOWED_NO)
				{
					throw new SecurityException('User not allowed to modify this object', array('class' => $sClass, 'id' => $id));
				}
				
				// Process request now
				$oObj->DisplayOperationForm($oP, $oAppContext, $operation);
			}
		break; // End case shrink
		
		///////////////////////////////////////////////////////////////////////////////////////////
		
		case 'doshrinkblock':	// Apply shrink for a block
		case 'doshrinksubnet':	// Apply shrink for a subnet
			$sClass = utils::ReadPostedParam('class', '', 'class');
			$id = utils::ReadPostedParam('id', '');
			$sTransactionId = utils::ReadPostedParam('transaction_id', '');
			
			// Check if right parameters have been given
			if ( empty($sClass) || empty($id))
			{
				throw new ApplicationException(Dict::Format('UI:Error:2ParametersMissing', 'class', 'id'));
			}
			if (($sClass != 'IPv4Block') && ($sClass != 'IPv6Block') && ($sClass != 'IPv4Subnet'))
			{
				throw new ApplicationException(Dict::Format('UI:Error:WrongActionForClass', $operation, $sClass));
			}
						
			// Object does exist. It has already been checked in action 'split' but check anyway.
			$oObj = MetaModel::GetObject($sClass, $id, true /* MustBeFound */);
			
			// Make sure we don't follow the same path twice in a row.
			$sClassLabel = MetaModel::GetName($sClass);
			if (!utils::IsTransactionValid($sTransactionId, false))
			{
				$oP->set_title(Dict::Format('UI:ModificationPageTitle_Object_Class', $oObj->GetName(), $sClassLabel));
				$oP->p("<strong>".Dict::S('UI:Error:ObjectAlreadyUpdated')."</strong>\n");
			}
			else
			{
				$aPostedParam = $oObj->GetPostedParam($operation);
					
				// Make sure object can be shrunk
				$sErrorString = $oObj->DoCheckToShrink($aPostedParam);
				if ($sErrorString != '')
				{
					// Found issues, explain and give the user another chance
					$sIssueDesc = Dict::Format('UI:IPManagement:Action:Shrink:'.$sClass.':CannotBeShrunk', $sErrorString);
					$sMessage = "<div class=\"header_message message_error teemip_message_error\">".$sIssueDesc."</div>";
					$oP->add($sMessage);
					
					$sNextOperation = $oObj->GetNextOperation($operation);
					$oObj->DisplayOperationForm($oP, $oAppContext, $sNextOperation, $aPostedParam);
				}
				else
				{
					// Set page titles
					$oP->set_title(Dict::Format('UI:IPManagement:Action:Shrink:'.$sClass.':PageTitle_Object_Class', $oObj->GetName(), $sClassLabel));
					$oP->add("<div class=\"page_header teemip_page_header\">\n");
					if ($sClass == 'IPv4Subnet')
					{
						$oP->add("<h1>".$oObj->GetIcon()."&nbsp;".Dict::Format('UI:IPManagement:Action:Shrink:'.$sClass.':Done', $sClassLabel, $oObj->GetName(), $aPostedParam['scale_id'])."</h1>\n");
					}
					else
					{
						$oP->add("<h1>".$oObj->GetIcon()."&nbsp;".Dict::Format('UI:IPManagement:Action:Shrink:'.$sClass.':Done', $sClassLabel, $oObj->GetName())."</h1>\n");
					}
					$oP->add("</div>\n");
					
					// Shrink block and display result
					$oSet = $oObj->DoShrink($aPostedParam);
					$oBlock = new DisplayBlock($oSet->GetFilter(), 'list', false);
					$oBlock->Display($oP, 'shrink_result', array('display_limit' => false, 'menu' => false));

					// Close transaction
					utils::RemoveTransaction($sTransactionId);
				}
			}
		break; // End case doshrink
		
		///////////////////////////////////////////////////////////////////////////////////////////
		
		case 'splitblock':	// Split a block
		case 'splitsubnet':	// Split a subnet
			$sClass = utils::ReadParam('class', '', false, 'class');
			$id = utils::ReadParam('id', '');
			// Check if right parameters have been given
			if ( empty($sClass) || empty($id))
			{
				throw new ApplicationException(Dict::Format('UI:Error:2ParametersMissing', 'class', 'id'));
			}
			if (($sClass != 'IPv4Block') && ($sClass != 'IPv6Block') && ($sClass != 'IPv4Subnet'))
			{
				throw new ApplicationException(Dict::Format('UI:Error:WrongActionForClass', $operation, $sClass));
			}
			
			// Check if the object exists
			$oObj = MetaModel::GetObject($sClass, $id, false /* MustBeFound */);
			if (is_null($oObj))
			{
				$oP->set_title(Dict::S('UI:ErrorPageTitle'));
				$oP->P(Dict::S('UI:ObjectDoesNotExist'));
			}
			else
			{
				// The object can be read - Check now that user is allowed to modify it
				$oSet = CMDBObjectSet::FromObject($oObj);
				if (UserRights::IsActionAllowed($sClass, UR_ACTION_MODIFY, $oSet) == UR_ALLOWED_NO)
				{
					throw new SecurityException('User not allowed to modify this object', array('class' => $sClass, 'id' => $id));
				}
				
				// Process request now
				$oObj->DisplayOperationForm($oP, $oAppContext, $operation);
			}
		break; // End case split
		
		///////////////////////////////////////////////////////////////////////////////////////////
		
		case 'dosplitblock':		// Apply split for a block 
		case 'dosplitsubnet':	// Apply split for a subnet
			$sClass = utils::ReadPostedParam('class', '', 'class');
			$id = utils::ReadPostedParam('id', '');
			$sTransactionId = utils::ReadPostedParam('transaction_id', '');
			
			// Check if right parameters have been given
			if ( empty($sClass) || empty($id))
			{
				throw new ApplicationException(Dict::Format('UI:Error:2ParametersMissing', 'class', 'id'));
			}
			if (($sClass != 'IPv4Block') && ($sClass != 'IPv6Block') && ($sClass != 'IPv4Subnet'))
			{
				throw new ApplicationException(Dict::Format('UI:Error:WrongActionForClass', $operation, $sClass));
			}
			
			// Object does exist. It has already been checked in action 'split' but check anyway.
			$oObj = MetaModel::GetObject($sClass, $id, true /* MustBeFound */);
			
			// Make sure we don't follow the same path twice in a row.
			$sClassLabel = MetaModel::GetName($sClass);
			if (!utils::IsTransactionValid($sTransactionId, false))
			{
				$oP->set_title(Dict::Format('UI:ModificationPageTitle_Object_Class', $oObj->GetName(), $sClassLabel));
				$oP->p("<strong>".Dict::S('UI:Error:ObjectAlreadyUpdated')."</strong>\n");
			}
			else
			{
				$aPostedParam = $oObj->GetPostedParam($operation);

 				// Make sure object can be split
				$sErrorString = $oObj->DoCheckToSplit($aPostedParam);
				if ($sErrorString != '')
				{
					// Found issues, explain and give the user another chance
					$sIssueDesc = Dict::Format('UI:IPManagement:Action:Split:'.$sClass.':CannotBeSplit', $sErrorString);
					$sMessage = "<div class=\"header_message message_error teemip_message_error\">".$sIssueDesc."</div>";
					$oP->add($sMessage);
					
					$sNextOperation = $oObj->GetNextOperation($operation);
					$oObj->DisplayOperationForm($oP, $oAppContext, $sNextOperation, $aPostedParam);
				}
				else
				{
					// Set page titles
					$oP->set_title(Dict::Format('UI:IPManagement:Action:Split:'.$sClass.':PageTitle_Object_Class', $oObj->GetName(), $sClassLabel));
					$oP->add("<div class=\"page_header teemip_page_header\">\n");
					if ($sClass == 'IPv4Subnet')
					{
						$oP->add("<h1>".$oObj->GetIcon()."&nbsp;".Dict::Format('UI:IPManagement:Action:Split:'.$sClass.':Done', $sClassLabel, $oObj->GetName(), $aPostedParam['scale_id'])."</h1>\n");
					}
					else
					{
						$oP->add("<h1>".$oObj->GetIcon()."&nbsp;".Dict::Format('UI:IPManagement:Action:Split:'.$sClass.':Done', $sClassLabel, $oObj->GetName())."</h1>\n");
					}
					$oP->add("</div>\n");
					
					// Split block and display result
					$oSet = $oObj->DoSplit($aPostedParam);
					$oBlock = new DisplayBlock($oSet->GetFilter(), 'list', false);
					$oBlock->Display($oP, 'split_result', array('display_limit' => false, 'menu' => false));

					// Close transaction
					utils::RemoveTransaction($sTransactionId);
				}
			}
		break; // End case dosplit
				
		///////////////////////////////////////////////////////////////////////////////////////////
		
		case 'expandblock':		// Expand a block	 
		case 'expandsubnet':	// Expand a subnet 
			$sClass = utils::ReadParam('class', '', false, 'class');
			$id = utils::ReadParam('id', '');
			// Check if right parameters have been given
			if ( empty($sClass) || empty($id))
			{
				throw new ApplicationException(Dict::Format('UI:Error:2ParametersMissing', 'class', 'id'));
			}
			if (($sClass != 'IPv4Block') && ($sClass != 'IPv6Block') && ($sClass != 'IPv4Subnet'))
			{
				throw new ApplicationException(Dict::Format('UI:Error:WrongActionForClass', $operation, $sClass));
			}
			
			// Check if the object exists
			$oObj = MetaModel::GetObject($sClass, $id, false /* MustBeFound */);
			if (is_null($oObj))
			{
				$oP->set_title(Dict::S('UI:ErrorPageTitle'));
				$oP->P(Dict::S('UI:ObjectDoesNotExist'));
			}
			else
			{
				// The object can be read - Check now that user is allowed to modify it
				$oSet = CMDBObjectSet::FromObject($oObj);
				if (UserRights::IsActionAllowed($sClass, UR_ACTION_MODIFY, $oSet) == UR_ALLOWED_NO)
				{
					throw new SecurityException('User not allowed to modify this object', array('class' => $sClass, 'id' => $id));
				}
				
				// Process request now
				$oObj->DisplayOperationForm($oP, $oAppContext, $operation);
			}
		break; // End case expand
		
		///////////////////////////////////////////////////////////////////////////////////////////
		
		case 'doexpandblock':	// Apply expand block command
		case 'doexpandsubnet':	// Apply expand a subnet
			$sClass = utils::ReadPostedParam('class', '', 'class');
			$id = utils::ReadPostedParam('id', '');
			$sTransactionId = utils::ReadPostedParam('transaction_id', '');
			
			// Check if right parameters have been given
			if ( empty($sClass) || empty($id))
			{
				throw new ApplicationException(Dict::Format('UI:Error:2ParametersMissing', 'class', 'id'));
			}
			if (($sClass != 'IPv4Block') && ($sClass != 'IPv6Block') && ($sClass != 'IPv4Subnet'))
			{
				throw new ApplicationException(Dict::Format('UI:Error:WrongActionForClass', $operation, $sClass));
			}
			
			// Object does exist. It has already been checked in action 'expand' but check anyway.
			$oObj = MetaModel::GetObject($sClass, $id, true /* MustBeFound */);
			
			// Make sure we don't follow the same path twice in a row.
			$sClassLabel = MetaModel::GetName($sClass);
			if (!utils::IsTransactionValid($sTransactionId, false))
			{
				$oP->set_title(Dict::Format('UI:ModificationPageTitle_Object_Class', $oObj->GetName(), $sClassLabel));
				$oP->p("<strong>".Dict::S('UI:Error:ObjectAlreadyUpdated')."</strong>\n");
			}
			else
			{
				$aPostedParam = $oObj->GetPostedParam($operation);
				
				// Make sure object can be expanded
				$sErrorString = $oObj->DoCheckToExpand($aPostedParam);
				if ($sErrorString != '')
				{
					// Found issues, explain and give the user another chance
					$sIssueDesc = Dict::Format('UI:IPManagement:Action:Expand:'.$sClass.':CannotBeExpanded', $sErrorString);
					$sMessage = "<div class=\"header_message message_error teemip_message_error\">".$sIssueDesc."</div>";
					$oP->add($sMessage);
					
					$sNextOperation = $oObj->GetNextOperation($operation);
					$oObj->DisplayOperationForm($oP, $oAppContext, $sNextOperation, $aPostedParam);
				}
				else
				{
					// Set page titles
					$oP->set_title(Dict::Format('UI:IPManagement:Action:Expand:'.$sClass.':PageTitle_Object_Class', $oObj->GetName(), $sClassLabel));
					$oP->add("<div class=\"page_header teemip_page_header\">\n");
					if ($sClass == 'IPv4Subnet')
					{
						$oP->add("<h1>".$oObj->GetIcon()."&nbsp;".Dict::Format('UI:IPManagement:Action:Expand:'.$sClass.':Done', $sClassLabel, $oObj->GetName(), $aPostedParam['scale_id'])."</h1>\n");
					}
					else
					{
						$oP->add("<h1>".$oObj->GetIcon()."&nbsp;".Dict::Format('UI:IPManagement:Action:Expand:'.$sClass.':Done', $sClassLabel, $oObj->GetName())."</h1>\n");
					}
					$oP->add("</div>\n");
					
					// Expand block and display result
					$oSet = $oObj->DoExpand($aPostedParam);
					$oBlock = new DisplayBlock($oSet->GetFilter(), 'list', false);
					$oBlock->Display($oP, 'expand_result', array('display_limit' => false, 'menu' => false));

					// Close transaction
					utils::RemoveTransaction($sTransactionId);
				}
			}
		break; // End case doexpand
		
		///////////////////////////////////////////////////////////////////////////////////////////
		
		case 'csvexportips':	// Export IPs of a subnet or a range in csv window
			$sClass = utils::ReadParam('class', '', false, 'class');
			$id = utils::ReadParam('id', '');
			// Check if right parameters have been given
			if ( empty($sClass) || empty($id))
			{
				throw new ApplicationException(Dict::Format('UI:Error:2ParametersMissing', 'class', 'id'));
			}
			if (($sClass != 'IPv4Subnet') && ($sClass != 'IPv6Subnet') && ($sClass != 'IPv4Range') && ($sClass != 'IPv6Range'))
			{
				throw new ApplicationException(Dict::Format('UI:Error:WrongActionForClass', $operation, $sClass));
			}
			
			// Check if the object exists
			$oObj = MetaModel::GetObject($sClass, $id, false /* MustBeFound */);
			if (is_null($oObj))
			{
				$oP->set_title(Dict::S('UI:ErrorPageTitle'));
				$oP->P(Dict::S('UI:ObjectDoesNotExist'));
			}
			else
			{
				// The object can be read - Process request now
				$iSize = $oObj->GetSize();
				if ($iSize >= MAX_NB_OF_IPS_TO_DISPLAY)
				{
					// Export subset of IPs only as size is too big to export all IPs once
					$oObj->DisplayOperationForm($oP, $oAppContext, $operation);
				}
				else
				{
					// Export all IPs once
					$sClassLabel = MetaModel::GetName($sClass);

					// No search bar (2.5 standard)
					
					// Display action menu
					$oSingletonFilter = new DBObjectSearch($sClass);
					$oSingletonFilter->AddCondition('id', $oObj->GetKey(), '=');
					$oBlock = new MenuBlock($oSingletonFilter, 'details', false);
					$oBlock->Display($oP, -1);
					
					// Set titles
					$oObj->SetPageTitles($oP, 'UI:IPManagement:Action:CsvExportIps:'.$sClass.':');
					
					// Display text area
					$sParameter = array ('first_ip' => '', 'last_ip' => '');
					$oP->add("<div id=\"3\" class=\"display_block\">\n"); 
					$oP->add("<textarea>\n"); 
					$sHtml = $oObj->GetIPsAsCSV($sParameter);
					$oP->add($sHtml);
					$oP->add("</textarea>\n");
					$oP->add("</div>\n");
					
					// Adjust the size of the block
					$oP->add_ready_script(" $('#3>textarea').height($('#3').parent().height() - 220).width( $('#3').parent().width() - 30);");
				}
			}
		break; // End case csvexportips
		
		///////////////////////////////////////////////////////////////////////////////////////////
		
		case 'docsvexportips':	// Apply csv export ips action
			$sClass = utils::ReadParam('class', '', false, 'class');
			$id = utils::ReadParam('id', '');
			$sTransactionId = utils::ReadPostedParam('transaction_id', '');
			
			// Check if right parameters have been given
			if ( empty($sClass) || empty($id))
			{
				throw new ApplicationException(Dict::Format('UI:Error:2ParametersMissing', 'class', 'id'));
			}
			if (($sClass != 'IPv4Subnet') && ($sClass != 'IPv6Subnet') && ($sClass != 'IPv4Range') && ($sClass != 'IPv6Range'))
			{
				throw new ApplicationException(Dict::Format('UI:Error:WrongActionForClass', $operation, $sClass));
			}
			
			// Check if the object exists
			$oObj = MetaModel::GetObject($sClass, $id, true /* MustBeFound */);

			// Make sure we don't follow the same path twice in a row.
			$sClassLabel = MetaModel::GetName($sClass);
			if (!utils::IsTransactionValid($sTransactionId, false))
			{
				$oP->set_title(Dict::Format('UI:ModificationPageTitle_Object_Class', $oObj->GetName(), $sClassLabel));
				$oP->p("<strong>".Dict::S('UI:Error:ObjectAlreadyUpdated')."</strong>\n");
			}
			else
			{
				$aPostedParam = $oObj->GetPostedParam($operation);
				
				// Make sure range can be exported as csv
				$sErrorString = $oObj->DoCheckToCsvExportIps($aPostedParam);
				if ($sErrorString != '')
				{
					// Found issues, explain and give the user another chance
					$sIssueDesc = Dict::Format('UI:IPManagement:Action:DoCsvExportIps:'.$sClass.':CannotBeListed', $sErrorString);
					$sMessage = "<div class=\"header_message message_error teemip_message_error\">".$sIssueDesc."</div>";
					$oP->add($sMessage);
					
					$sNextOperation = $oObj->GetNextOperation($operation);
					$oObj->DisplayOperationForm($oP, $oAppContext, $sNextOperation, $aPostedParam);
				}
				else
				{
					// No search bar (2.5 standard)
					
					// Display action menu
					$oSingletonFilter = new DBObjectSearch($sClass);
					$oSingletonFilter->AddCondition('id', $oObj->GetKey(), '=');
					$oBlock = new MenuBlock($oSingletonFilter, 'details', false);
					$oBlock->Display($oP, -1);
					
					// Set titles
					$oObj->SetPageTitles($oP, 'UI:IPManagement:Action:DoCsvExportIps:'.$sClass.':');
					
					// Display text area
					$oP->add("<div id=\"3\" class=\"display_block\">\n"); 
					$oP->add("<textarea>\n"); 
					$sHtml = $oObj->GetIPsAsCSV($aPostedParam);
					$oP->add($sHtml);
					$oP->add("</textarea>\n");
					$oP->add("</div>\n");
					
					// Adjust the size of the block
					$oP->add_ready_script(" $('#3>textarea').height($('#3').parent().height() - 220).width( $('#3').parent().width() - 30);");
				}
			}
		break; // End case docsvexportips
		
		///////////////////////////////////////////////////////////////////////////////////////////
		
		case 'calculator':	// Provides IP related calculations
			$sClass = utils::ReadParam('class', '', false, 'class');
			if (!empty($sClass))
			{
				if (($sClass != 'IPv4Subnet') && ($sClass != 'IPv6Subnet'))
				{
					throw new ApplicationException(Dict::Format('UI:Error:WrongActionForClass', $operation, $sClass));
				}

				$id = utils::ReadParam('id', '');
				// Id may be null. In that case a temporary object is created.
				if ( empty($id))
				{
					$oObj = MetaModel::NewObject($sClass);
					$id = $oObj->GetKey();
				}
				else
				{
					// Check if the object exists
					$oObj = MetaModel::GetObject($sClass, $id, false /* MustBeFound */);
					if (is_null($oObj))
					{
						$oObj = MetaModel::NewObject($sClass);
						$id = $oObj->GetKey();
					}
				}

				// Display calculation page
				$oObj->DisplayOperationForm($oP, $oAppContext, $operation);
			}
			else
			{
				// Select the subnet class to calculate
				$sClassLabel = MetaModel::GetName('IPSubnet');
				$sClassIcon = MetaModel::GetClassIcon('IPv4Subnet');
				$sHeaderTitle = Dict::S('UI:IPManagement:Action:Calculator:IPSubnet');
				$oP->set_title($sHeaderTitle);
				$oP->add(<<<HTML
	<!-- Display title -->
	<div class="page_header teemip_page_header">
		<h1>$sClassIcon $sHeaderTitle</h1>
	</div>
	<!-- Beginning of wizContainer -->
	<div class="wizContainer">
HTML
				);
				$sFormAction= utils::GetAbsoluteUrlModulesRoot()."/teemip-ip-mgmt/ui.teemip-ip-mgmt.php";
				$oP->add("<form action=\"$sFormAction\" id=\"form_for_subnet_calculator\" enctype=\"multipart/form-data\" method=\"post\" onSubmit=\"return OnSubmit('form_for_subnet_calculator');\">\n");
				$oP->add('<p>'.Dict::S('UI:IPManagement:Action:Calculator:IPSubnet:SelectSubnetType'));
				$oP->add($oAppContext->GetForForm());
				$oP->add("<input type=\"hidden\" name=\"operation\" value=\"calculator\">\n");
				$oP->add('<select name="class">');
				$aPossibleClasses = array('IPv4Subnet' => MetaModel::GetName('IPv4Subnet'), 'IPv6Subnet' => MetaModel::GetName('IPv6Subnet'));
				foreach($aPossibleClasses as $sClassName => $sClassLabel)
				{
					$sSelected = ($sClassName == $sClass) ? 'selected' : '';
					$oP->add("<option $sSelected value=\"$sClassName\">$sClassLabel</option>");
				}
				$oP->add('</select>');
				$oP->add("&nbsp; <input type=\"submit\" value=\"".Dict::S('UI:Button:Apply')."\"></p>");
				$oP->add('</form>');
				$oP->add(<<<HTML
	</div><!-- End of wizContainer -->
HTML
				);
			}
		break; // End case calculator
		
		///////////////////////////////////////////////////////////////////////////////////////////
		
		case 'docalculator':	// Calculates subnet parameters
			$sClass = utils::ReadParam('class', '', false, 'class');
			$id = utils::ReadParam('id', '');
			$sTransactionId = utils::ReadPostedParam('transaction_id', '');

			// Check if right parameters have been given
			if ( empty($sClass) || empty($id))
			{
				throw new ApplicationException(Dict::Format('UI:Error:2ParametersMissing', 'class', 'id'));
			}
			if (($sClass != 'IPv4Subnet') && ($sClass != 'IPv6Subnet'))
			{
				throw new ApplicationException(Dict::Format('UI:Error:WrongActionForClass', $operation, $sClass));
			}
			
			if ($id > 0)
			{
				// Check if the object exists
				$oObj = MetaModel::GetObject($sClass, $id, false /* MustBeFound */);
				if (is_null($oObj))
				{
					$oObj = MetaModel::NewObject($sClass);
					$id = $oObj->GetKey();
				}
			}
			else
			{
				$oObj = MetaModel::NewObject($sClass);
				$id = $oObj->GetKey();
			}

			// Display calculator output
			$sClassLabel = MetaModel::GetName($sClass);
			$aPostedParam = $oObj->GetPostedParam($operation);
			
			// Check calculator inputs
			$sErrorString = $oObj->DoCheckCalculatorInputs($aPostedParam);
			if ($sErrorString != '')
			{
				// Found issues, explain and give the user another chance
				$sIssueDesc = Dict::Format('UI:IPManagement:Action:DoCalculator:'.$sClass.':CannotRun', $sErrorString);
				$sMessage = "<div class=\"header_message message_error teemip_message_error\">".$sIssueDesc."</div>";
				$oP->add($sMessage);
				
				$sNextOperation = $oObj->GetNextOperation($operation);
				$oObj->DisplayOperationForm($oP, $oAppContext, $sNextOperation, $aPostedParam);
			}
			else
			{	
				if ($id > 0)
				{
					// No search bar (2.5 standard)
						
					// Display action menu
					$oSingletonFilter = new DBObjectSearch($sClass);
					$oSingletonFilter->AddCondition('id', $oObj->GetKey(), '=');
					$oBlock = new MenuBlock($oSingletonFilter, 'details', false);
					$oBlock->Display($oP, -1);
				}
				
				// Set titles
				$oObj->SetPageTitles($oP, 'UI:IPManagement:Action:DoCalculator:'.$sClass.':');
	
				// Display result
				$oObj->DisplayCalculatorOutput($oP, $oAppContext, $aPostedParam);;
				$oP->add_ready_script("\$('#tree ul').treeview();\n");
				$oP->add("<div id=\"dialog_content\"/>\n");
			}
		break; // End case docalculator
		
		///////////////////////////////////////////////////////////////////////////////////////////
				
		case 'delegate':	// Delegates block to child organization
			$sClass = utils::ReadParam('class', '', false, 'class');
			$id = utils::ReadParam('id', '');
			// Check if right parameters have been given
			if ( empty($sClass))
			{
				throw new ApplicationException(Dict::Format('UI:Error:1ParametersMissing', 'class'));
			}
			if (!in_array($sClass, array('Domain', 'IPv4Block', 'IPv6Block')))
			{
				throw new ApplicationException(Dict::Format('UI:Error:WrongActionForClass', $operation, $sClass));
			}
			
			// Check if the object exists
			$oObj = MetaModel::GetObject($sClass, $id, false /* MustBeFound */);
			if (is_null($oObj))
			{
				$oP->set_title(Dict::S('UI:ErrorPageTitle'));
				$oP->P(Dict::S('UI:ObjectDoesNotExist'));
			}
			else
			{
				// The object can be read - Check now that user is allowed to modify it
				$oSet = CMDBObjectSet::FromObject($oObj);
				if (UserRights::IsActionAllowed($sClass, UR_ACTION_MODIFY, $oSet) == UR_ALLOWED_NO)
				{
					throw new SecurityException('User not allowed to modify this object', array('class' => $sClass, 'id' => $id));
				}
				
				// Process request now
				$oObj->DisplayOperationForm($oP, $oAppContext, $operation);
			}
		break; // End case delegate
		
		///////////////////////////////////////////////////////////////////////////////////////////

		case 'dodelegate':	// Apply delegate a block
			$sClass = utils::ReadPostedParam('class', '', 'class');
			$id = utils::ReadPostedParam('id', '');
			$sTransactionId = utils::ReadPostedParam('transaction_id', '');
			
			// Check if right parameters have been given
			if ( empty($sClass) || empty($id))
			{
				throw new ApplicationException(Dict::Format('UI:Error:2ParametersMissing', 'class', 'id'));
			}
			if (!in_array($sClass, array('Domain', 'IPv4Block', 'IPv6Block')))
			{
				throw new ApplicationException(Dict::Format('UI:Error:WrongActionForClass', $operation, $sClass));
			}
			
			// Object does exist. It has already been checked in action delegate but check anyway.
			$oObj = MetaModel::GetObject($sClass, $id, true /* MustBeFound */);
			
			// Make sure we don't follow the same path twice in a row.
			$sClassLabel = MetaModel::GetName($sClass);
			if (!utils::IsTransactionValid($sTransactionId, false))
			{
				$oP->set_title(Dict::Format('UI:ModificationPageTitle_Object_Class', $oObj->GetName(), $sClassLabel));
				$oP->p("<strong>".Dict::S('UI:Error:ObjectAlreadyUpdated')."</strong>\n");
			}
			else
			{
				$aPostedParam = $oObj->GetPostedParam($operation);
				
				// Make sure object can be delegated
				$sErrorString = $oObj->DoCheckToDelegate($aPostedParam);
				if ($sErrorString != '')
				{
					// Found issues, explain and give the user another chance
					$sIssueDesc = Dict::Format('UI:IPManagement:Action:Delegate:'.$sClass.':CannotBeDelegated', $sErrorString);
					$sMessage = "<div class=\"header_message message_error teemip_message_error\">".$sIssueDesc."</div>";
					$oP->add($sMessage);

					$sNextOperation = $oObj->GetNextOperation($operation);
					$oObj->DisplayOperationForm($oP, $oAppContext, $sNextOperation, $aPostedParam);
				}
				else
				{
					// Set page titles
					$oP->set_title(Dict::Format('UI:IPManagement:Action:Delegate:'.$sClass.':PageTitle_Object_Class', $oObj->GetName(), $sClassLabel));
					$oP->add("<div class=\"page_header teemip_page_header\">\n");
					$oP->add("<h1>".$oObj->GetIcon()."&nbsp;".Dict::Format('UI:IPManagement:Action:Delegate:'.$sClass.':Done', $sClassLabel, $oObj->GetName())."</h1>\n");
					$oP->add("</div>\n");
					
					// Delegate block and display result in details form
					$oSet = $oObj->DoDelegate($aPostedParam);
					$oBlock = new DisplayBlock($oSet->GetFilter(), 'list', false);
					$oBlock->Display($oP, 'delegate_result', array('display_limit' => false, 'menu' => false));

					// Close transaction
					utils::RemoveTransaction($sTransactionId);
				}
			}
		break; // End case dodelegate
		
		///////////////////////////////////////////////////////////////////////////////////////////
		
		case 'undelegate':	// Delegates block to child organization
			$sClass = utils::ReadParam('class', '', false, 'class');
			$id = utils::ReadParam('id', '');
			// Check if right parameters have been given
			if ( empty($sClass))
			{
				throw new ApplicationException(Dict::Format('UI:Error:1ParametersMissing', 'class'));
			}
			if (!in_array($sClass, array('Domain', 'IPv4Block', 'IPv6Block')))
			{
				throw new ApplicationException(Dict::Format('UI:Error:WrongActionForClass', $operation, $sClass));
			}
				
			// Check if the object exists
			$oObj = MetaModel::GetObject($sClass, $id, false /* MustBeFound */);
			if (is_null($oObj))
			{
				$oP->set_title(Dict::S('UI:ErrorPageTitle'));
				$oP->P(Dict::S('UI:ObjectDoesNotExist'));
			}
			else
			{
				// The object can be read - Check now that user is allowed to modify it
				$oSet = CMDBObjectSet::FromObject($oObj);
				if (UserRights::IsActionAllowed($sClass, UR_ACTION_MODIFY, $oSet) == UR_ALLOWED_NO)
				{
					throw new SecurityException('User not allowed to modify this object', array('class' => $sClass, 'id' => $id));
				}
		
				// Make sure object can be undelegated
				$sErrorString = $oObj->DoCheckToUndelegate(array());
				if ($sErrorString != '')
				{
					// Found issues: explain and display block again					
					// No search bar (2.5 standard)

					$sIssueDesc = Dict::Format('UI:IPManagement:Action:Undelegate:'.$sClass.':CannotBeUndelegated', $sErrorString);
					cmdbAbstractObject::SetSessionMessage($sClass, $id, 'undelegate', $sIssueDesc, 'error', 0, true /* must not exist */);
					$oObj->DisplayDetails($oP);
				}
				else
				{
					// Set page titles
					$sClassLabel = MetaModel::GetName($sClass);
					$oP->set_title(Dict::Format('UI:IPManagement:Action:Undelegate:'.$sClass.':PageTitle_Object_Class', $oObj->GetName(), $sClassLabel));
					$oP->add("<div class=\"page_header teemip_page_header\">\n");
					$oP->add("<h1>".$oObj->GetIcon()."&nbsp;".Dict::Format('UI:IPManagement:Action:Undelegate:'.$sClass.':Done', $sClassLabel, $oObj->GetName())."</h1>\n");
					$oP->add("</div>\n");
					
					// Undelegate block and display result in details form
					$oSet = $oObj->DoUndelegate(array());
					$oBlock = new DisplayBlock($oSet->GetFilter(), 'list', false);
					$oBlock->Display($oP, 'undelegate_result', array('display_limit' => false, 'menu' => false));
				}
			}
		break; // End case undelegate

		///////////////////////////////////////////////////////////////////////////////////////////

		case 'allocateip':	// Allocate existing IP (not already allocated) to an existing CI
			$sClass = utils::ReadParam('class', '', false, 'class');
			$id = utils::ReadParam('id', '');
			// Check if right parameters have been given
			if ( empty($sClass))
			{
				throw new ApplicationException(Dict::Format('UI:Error:1ParametersMissing', 'class'));
			}
			if (($sClass != 'IPv4Address') && ($sClass != 'IPv6Address'))
			{
				throw new ApplicationException(Dict::Format('UI:Error:WrongActionForClass', $operation, $sClass));
			}

			// Check if the object exists
			$oObj = MetaModel::GetObject($sClass, $id, false /* MustBeFound */);
			if (is_null($oObj))
			{
				$oP->set_title(Dict::S('UI:ErrorPageTitle'));
				$oP->P(Dict::S('UI:ObjectDoesNotExist'));
			}
			else
			{
				// The object can be read - Check now that user is allowed to modify it
				$oSet = CMDBObjectSet::FromObject($oObj);
				if (UserRights::IsActionAllowed($sClass, UR_ACTION_MODIFY, $oSet) == UR_ALLOWED_NO)
				{
					throw new SecurityException('User not allowed to modify this object', array('class' => $sClass, 'id' => $id));
				}

				// Process request now
				$oObj->DisplayOperationForm($oP, $oAppContext, $operation);
			}
			break; // End case allocateip

		///////////////////////////////////////////////////////////////////////////////////////////

		case 'doallocateip':	// Apply allocate IP
			$sClass = utils::ReadPostedParam('class', '', 'class');
			$id = utils::ReadPostedParam('id', '');
			$sTransactionId = utils::ReadPostedParam('transaction_id', '');

			// Check if right parameters have been given
			if ( empty($sClass) || empty($id))
			{
				throw new ApplicationException(Dict::Format('UI:Error:2ParametersMissing', 'class', 'id'));
			}
			if (($sClass != 'IPv4Address') && ($sClass != 'IPv6Address'))
			{
				throw new ApplicationException(Dict::Format('UI:Error:WrongActionForClass', $operation, $sClass));
			}

			// Object does exist. It has already been checked in action allocate but check anyway.
			$oObj = MetaModel::GetObject($sClass, $id, true /* MustBeFound */);

			// Make sure we don't follow the same path twice in a row.
			$sClassLabel = MetaModel::GetName($sClass);
			if (!utils::IsTransactionValid($sTransactionId, false))
			{
				$oP->set_title(Dict::Format('UI:ModificationPageTitle_Object_Class', $oObj->GetName(), $sClassLabel));
				$oP->p("<strong>".Dict::S('UI:Error:ObjectAlreadyUpdated')."</strong>\n");
			}
			else
			{
				$aPostedParam = $oObj->GetPostedParam($operation);

				// Make sure object can be delegated
				$sErrorString = $oObj->DoCheckToAllocate($aPostedParam);
				if ($sErrorString != '')
				{
					// Found issues, explain and give the user another chance
					$sIssueDesc = Dict::Format('UI:IPManagement:Action:Allocate:IPAddress:CannotAllocateCI', $sErrorString);
					$sMessage = "<div class=\"header_message message_error teemip_message_error\">".$sIssueDesc."</div>";
					$oP->add($sMessage);

					$sNextOperation = $oObj->GetNextOperation($operation);
					$oObj->DisplayOperationForm($oP, $oAppContext, $sNextOperation, $aPostedParam);
				}
				else
				{
					// Set page titles
					$oP->set_title(Dict::Format('UI:IPManagement:Action:Allocate:'.$sClass.':PageTitle_Object_Class', $oObj->GetName(), $sClassLabel));
					$oP->add("<div class=\"page_header teemip_page_header\">\n");
					$oP->add("<h1>".$oObj->GetIcon()."&nbsp;".Dict::Format('UI:IPManagement:Action:Allocate:'.$sClass.':Done', $sClassLabel, $oObj->GetName())."</h1>\n");
					$oP->add("</div>\n");

					// Delegate block and display result in details form
					$oSet = $oObj->DoAllocate($aPostedParam);
					$oBlock = new DisplayBlock($oSet->GetFilter(), 'list', false);
					$oBlock->Display($oP, 'allocateip_result', array('display_limit' => false, 'menu' => false));

					// Close transaction
					utils::RemoveTransaction($sTransactionId);
				}
			}
			break; // End case doallocateip

		///////////////////////////////////////////////////////////////////////////////////////////

		case 'unallocateip':	// Unallocate existing allocated IP from a CI
			$sClass = utils::ReadParam('class', '', false, 'class');
			$id = utils::ReadParam('id', '');
			// Check if right parameters have been given
			if ( empty($sClass))
			{
				throw new ApplicationException(Dict::Format('UI:Error:1ParametersMissing', 'class'));
			}
			if (($sClass != 'IPv4Address') && ($sClass != 'IPv6Address'))
			{
				throw new ApplicationException(Dict::Format('UI:Error:WrongActionForClass', $operation, $sClass));
			}

			// Check if the object exists
			$oObj = MetaModel::GetObject($sClass, $id, false /* MustBeFound */);
			if (is_null($oObj))
			{
				$oP->set_title(Dict::S('UI:ErrorPageTitle'));
				$oP->P(Dict::S('UI:ObjectDoesNotExist'));
			}
			else
			{
				// The object can be read - Check now that user is allowed to modify it
				$oSet = CMDBObjectSet::FromObject($oObj);
				if (UserRights::IsActionAllowed($sClass, UR_ACTION_MODIFY, $oSet) == UR_ALLOWED_NO)
				{
					throw new SecurityException('User not allowed to modify this object', array('class' => $sClass, 'id' => $id));
				}

				// A revoir ->

				// Make sure object can be unallocated
				$sErrorString = $oObj->DoCheckToUnallocate(array());
				if ($sErrorString != '')
				{
					// Found issues: explain and display block again
					// No search bar (2.5 standard)

					$sIssueDesc = Dict::Format('UI:IPManagement:Action:Unallocate:IPAddress:CannotBeUnallocated', $sErrorString);
					cmdbAbstractObject::SetSessionMessage($sClass, $id, 'unallocate', $sIssueDesc, 'error', 0, true /* must not exist */);
					$oObj->DisplayDetails($oP);
				}
				else
				{
					// Set page titles
					$sClassLabel = MetaModel::GetName($sClass);
					$oP->set_title(Dict::Format('UI:IPManagement:Action:Unallocate:'.$sClass.':PageTitle_Object_Class', $oObj->GetName(), $sClassLabel));
					$oP->add("<div class=\"page_header teemip_page_header\">\n");
					$oP->add("<h1>".$oObj->GetIcon()."&nbsp;".Dict::Format('UI:IPManagement:Action:Unallocate:'.$sClass.':Done', $sClassLabel, $oObj->GetName())."</h1>\n");
					$oP->add("</div>\n");

					// Undelegate block and display result in details form
					$oSet = $oObj->DoUnallocate(array());
					$oBlock = new DisplayBlock($oSet->GetFilter(), 'list', false);
					$oBlock->Display($oP, 'unallocateip_result', array('display_limit' => false, 'menu' => false));
				}
			}
			break; // End case unallocateip

		///////////////////////////////////////////////////////////////////////////////////////////

		//case 'test':
		//	IPv6Address::IPv6CompressionMigration();

		case 'cancel':	// An action was cancelled
		case 'displaylist':
		default: // Menu node rendering (templates)
			ApplicationMenu::LoadAdditionalMenus();
			$oMenuNode = ApplicationMenu::GetMenuNode(ApplicationMenu::GetMenuIndexById(ApplicationMenu::GetActiveNodeId()));
			if (is_object($oMenuNode))
			{
				$oMenuNode->RenderContent($oP, $oAppContext->GetAsHash());
				$oP->set_title($oMenuNode->GetLabel());
			}
			break;
		
	}
	$oP->output(); // Display the whole content now !
}

catch(CoreException $e)
{
	require_once(APPROOT.'/setup/setuppage.class.inc.php');
	$oP = new SetupPage(Dict::S('UI:PageTitle:FatalError'));
	if ($e instanceof SecurityException)
	{
		$oP->add("<h1>".Dict::S('UI:SystemIntrusion')."</h1>\n");
	}
	else
	{
		$oP->add("<h1>".Dict::S('UI:FatalErrorMessage')."</h1>\n");
	}	
	$oP->error(Dict::Format('UI:Error_Details', $e->getHtmlDesc()));	
	$oP->output();
	
	if (MetaModel::IsLogEnabledIssue())
	{
		if (MetaModel::IsValidClass('EventIssue'))
		{
			try
			{
				$oLog = new EventIssue();
				
				$oLog->Set('message', $e->getMessage());
				$oLog->Set('userinfo', '');
				$oLog->Set('issue', $e->GetIssue());
				$oLog->Set('impact', 'Page could not be displayed');
				$oLog->Set('callstack', $e->getTrace());
				$oLog->Set('data', $e->getContextData());
				$oLog->DBInsertNoReload();
			}
			catch(Exception $e)
			{
				IssueLog::Error("Failed to log issue into the DB");
			}
		}
		
		IssueLog::Error($e->getMessage());
	}
	
	// For debugging only
	//throw $e;
}

catch(Exception $e)
{
	require_once(APPROOT.'/setup/setuppage.class.inc.php');
	$oP = new SetupPage(Dict::S('UI:PageTitle:FatalError'));
	$oP->add("<h1>".Dict::S('UI:FatalErrorMessage')."</h1>\n");	
	$oP->error(Dict::Format('UI:Error_Details', $e->getMessage()));	
	$oP->output();
	
	if (MetaModel::IsLogEnabledIssue())
	{
		if (MetaModel::IsValidClass('EventIssue'))
		{
			try
			{
				$oLog = new EventIssue();
				
				$oLog->Set('message', $e->getMessage());
				$oLog->Set('userinfo', '');
				$oLog->Set('issue', 'PHP Exception');
				$oLog->Set('impact', 'Page could not be displayed');
				$oLog->Set('callstack', $e->getTrace());
				$oLog->Set('data', array());
				$oLog->DBInsertNoReload();
			}
			catch(Exception $e)
			{
				IssueLog::Error("Failed to log issue into the DB");
			}
		}
		
		IssueLog::Error($e->getMessage());
	}
}
