<?php
/* Copyright (c) 1998-2015 ILIAS open source, Extended GPL, see docs/LICENSE */
require_once 'Services/Repository/classes/class.ilObjectPluginGUI.php';
require_once 'Services/PersonalDesktop/interfaces/interface.ilDesktopItemHandling.php';
require_once 'Services/Form/classes/class.ilPropertyFormGUI.php';
require_once 'Services/MediaObjects/classes/class.ilObjMediaObject.php';
require_once 'Services/MediaObjects/classes/class.ilObjMediaObjectGUI.php';
require_once dirname(__FILE__) . '/class.ilInteractiveVideoPlugin.php'; 
ilInteractiveVideoPlugin::getInstance()->includeClass('class.ilObjComment.php');
ilInteractiveVideoPlugin::getInstance()->includeClass('class.xvidUtils.php');

/**
 * Class ilObjInteractiveVideoGUI
 * @author               Nadia Ahmad <nahmad@databay.de>
 * @ilCtrl_isCalledBy    ilObjInteractiveVideoGUI: ilRepositoryGUI, ilAdministrationGUI, ilObjPluginDispatchGUI
 * @ilCtrl_Calls         ilObjInteractiveVideoGUI: ilPermissionGUI, ilInfoScreenGUI, ilObjectCopyGUI, ilRepositorySearchGUI, ilPublicUserProfileGUI, ilCommonActionDispatcherGUI, ilMDEditorGUI
 */
class ilObjInteractiveVideoGUI extends ilObjectPluginGUI implements ilDesktopItemHandling
{
	/**
	 * @object $objComment ilObjComment
	 */
	public $objComment;

	/**
	 * Functions that must be overwritten
	 */
	public function getType()
	{
		return 'xvid';
	}

	/**
	 * Cmd that will be redirected to after creation of a new object.
	 */
	public function getAfterCreationCmd()
	{
		return 'editProperties';
	}

	public function getStandardCmd()
	{
		return 'showContent';
	}

	/**
	 * @param string $cmd
	 * @throws ilException
	 */
	public function performCommand($cmd)
	{
		/**
		 * @var $ilTabs ilTabsGUI
		 * @var $tpl    ilTemplate
		 */
		global $ilTabs, $tpl;
		$tpl->setDescription($this->object->getDescription());

		$next_class = $this->ctrl->getNextClass($this);
		switch($next_class)
		{
			case 'ilmdeditorgui':
				$this->checkPermission('write');
				require_once 'Services/MetaData/classes/class.ilMDEditorGUI.php';
				$md_gui = new ilMDEditorGUI($this->object->getId(), 0, $this->object->getType());
				$md_gui->addObserver($this->object, 'MDUpdateListener', 'General');
				$ilTabs->setTabActive('meta_data');
				$this->ctrl->forwardCommand($md_gui);
				break;

			case 'ilpublicuserprofilegui':
				require_once 'Services/User/classes/class.ilPublicUserProfileGUI.php';
				$profile_gui = new ilPublicUserProfileGUI((int)$_GET['user']);
				$profile_gui->setBackUrl($this->ctrl->getLinkTarget($this, 'showContent'));
				$this->tpl->setContent($this->ctrl->forwardCommand($profile_gui));
				break;

			case 'ilcommonactiondispatchergui':
				require_once 'Services/Object/classes/class.ilCommonActionDispatcherGUI.php';
				$gui = ilCommonActionDispatcherGUI::getInstanceFromAjaxCall();
				$this->ctrl->forwardCommand($gui);
				break;

			default:
				switch($cmd)
				{
					case 'updateProperties':
					case 'editProperties':
					case 'confirmDeleteComment':
					case 'deleteComment':
						$this->checkPermission('write');
						$this->$cmd();
						break;

					case 'redrawHeaderAction':
					case 'addToDesk':
					case 'removeFromDesk':
					case 'showContent':
						if(in_array($cmd, array('addToDesk', 'removeFromDesk')))
						{
							$cmd .= 'Object';
						}
						$this->checkPermission('read');
						$this->$cmd();
						break;

					default:
						if(method_exists($this, $cmd))
						{
							$this->checkPermission('read');
							$this->$cmd();
						}
						else
						{
							throw new ilException(sprintf("Unsupported plugin command %s ind %s", $cmd, __METHOD__));
						}
						break;
				}
				break;
		}

		$this->addHeaderAction();
	}

	/**
	 * 
	 */
	public function showContent()
	{
		/**
		 * @var $tpl    ilTemplate
		 * @var $ilTabs ilTabsGUI
		 */
		global $tpl, $ilTabs;

		$ilTabs->activateTab('content');

		$tpl->addJavaScript($this->plugin->getDirectory() . '/js/jquery.scrollbox.js');
		$tpl->addCss($this->plugin->getDirectory() . '/templates/default/xvid.css');
		ilObjMediaObjectGUI::includePresentationJS($tpl);

		$video_tpl = new ilTemplate("tpl.video_tpl.html", true, true, $this->plugin->getDirectory());

		$mob_id = $this->object->getMobId();
		$mob_dir    = ilObjMediaObject::_getDirectory($mob_id);
		$media_item = ilMediaItem::_getMediaItemsOfMObId($mob_id, 'Standard');

		$video_tpl->setVariable('VIDEO_SRC', $mob_dir . '/' . $media_item['location']);
		$video_tpl->setVariable('VIDEO_TYPE', $media_item['format']);

		$this->objComment = new ilObjComment();
		$this->objComment->setObjId($this->object->getId());

		$stop_points = $this->objComment->getStopPoints();
		$video_tpl->setVariable('TXT_COMMENT', $this->plugin->txt('comment'));
		$video_tpl->setVariable('TXT_POST', $this->plugin->txt('post'));
		$video_tpl->setVariable('TXT_CANCEL', $this->plugin->txt('cancel'));

		$video_tpl->setVariable('STOP_POINTS', json_encode($stop_points));

		$comments = $this->objComment->getCommentTexts();
		$i        = 1;
		foreach($comments as $comment_text)
		{
			$video_tpl->setCurrentBlock('comments_list');
			$video_tpl->setVariable('COMMENT_INDEX', $i);
			$video_tpl->setVariable('COMMENT_TEXT', $comment_text);
			$video_tpl->parseCurrentBlock();
			$i++;
		}

		$video_tpl->setVariable('FORM_ACTION', $this->ctrl->getFormAction($this, 'postComment'));

		$tpl->setContent($video_tpl->get());
	}

	/**
	 * 
	 */
	public function postComment()
	{
		/**
		 * @var $ilUser ilObjUser
		 */
		global $ilUser;

		if(
			!isset($_POST['comment_text']) ||
			!is_string($_POST['comment_text']) ||
			!strlen(trim(ilUtil::stripSlashes($_POST['comment_text'])))
		)
		{
			ilUtil::sendFailure($this->plugin->txt('missing_comment_text'));
			$this->showContent();
			return;
		}

		if(!isset($_POST['comment_time']) || !strlen(trim(ilUtil::stripSlashes($_POST['comment_time']))))
		{
			ilUtil::sendFailure($this->plugin->txt('missing_stopping_point'));
			$this->showContent();
			return;
		}

		$comment = new ilObjComment();
		$comment->setObjId($this->object->getId());
		$comment->setUserId($ilUser->getId());
		$comment->setCommentText(trim(ilUtil::stripSlashes($_POST['comment_text'])));
		$comment->setCommentTime((float)$_POST['comment_time']);
		$comment->create();

		ilUtil::sendSuccess($this->lng->txt('saved_successfully'));
		$this->showContent();
	}

	/**
	 * 
	 */
	public function confirmDeleteComment()
	{
		/**
		 * @var $tpl    ilTemplate
		 * @var $ilTabs ilTabsGUI
		 */
		global $tpl, $ilTabs;

		$ilTabs->activateTab('editProperties');

		if(!isset($_POST['comment_id']) || !is_array($_POST['comment_id']) || !count($_POST['comment_id']))
		{
			ilUtil::sendFailure($this->lng->txt('select_one'));
			$this->editComments();
			return;
		}

		require_once 'Services/Utilities/classes/class.ilConfirmationGUI.php';
		$confirm = new ilConfirmationGUI();
		$confirm->setFormAction($this->ctrl->getFormAction($this, 'deleteComment'));
		$confirm->setHeaderText($this->plugin->txt('sure_delete_comment'));
		$confirm->setConfirm($this->lng->txt('confirm'), 'deleteComment');
		$confirm->setCancel($this->lng->txt('cancel'), 'editComments');

		// @todo: Check (in a separate loop) if all the comment ids belong to the current object context, otherwise show a failure message

		foreach($_POST['comment_id'] as $comment_id)
		{
			$confirm->addItem('comment_id[]', $comment_id, $this->object->getCommentTextById($comment_id));
		}
	
		$tpl->setContent($confirm->getHTML());
	}

	/**
	 * 
	 */
	public function deleteComment()
	{
		if(!isset($_POST['comment_id']) || !is_array($_POST['comment_id']) || !count($_POST['comment_id']))
		{
			ilUtil::sendFailure($this->lng->txt('select_one'));
			$this->editComments();
			return;
		}

		// @todo: Check (in a separate loop) if the comment ids belong to the current object context, otherwise show a failure message
		$this->object->deleteComments($_POST['comment_id']);

		// @todo: Print a success message (and directly add the language variable)
		ilUtil::sendSuccess($this->lng->txt(''));
		$this->editComments();
	}

	/**
	 * @return ilPropertyFormGUI
	 */
	private function initCommentForm()
	{
		// @todo: Why don't you add a "required" attribute to any of these fields?

		$form = new ilPropertyFormGUI();
		$form->setFormAction($this->ctrl->getFormAction($this, 'insertComment'));
		// @todo: Untranslated language variable
		$form->setTitle($this->plugin->txt('insert_comment'));

		$this->plugin->includeClass('class.ilTimeInputGUI.php');
		$time = new ilTimeInputGUI($this->lng->txt('time'), 'comment_time');
		$time->setShowTime(true);
		$time->setShowSeconds(true);
		$form->addItem($time);

		$comment = new ilTextAreaInputGUI($this->lng->txt('comment'), 'comment_text');
		$form->addItem($comment);

		$interactive = new ilCheckboxInputGUI($this->plugin->txt('interactive'), 'is_interactive');
		$form->addItem($interactive);
		return $form;
	}

	/**
	 * 
	 */
	public function showTutorInsertCommentForm()
	{
		/**
		 * @var $tpl    ilTemplate
		 * @var $ilTabs ilTabsGUI
		 */
		global $tpl, $ilTabs;

		$this->setSubTabs('editProperties');

		$ilTabs->activateTab('editProperties');
		$ilTabs->activateSubTab('editComments');

		$form = $this->initCommentForm();

		$form->addCommandButton('insertTutorComment', $this->lng->txt('insert'));
		$form->addCommandButton('editComments', $this->lng->txt('cancel'));

		$tpl->setContent($form->getHTML());
	}

	/**
	 * 
	 */
	public function showLearnerCommentForm()
	{
		/**
		 * @var $tpl    ilTemplate
		 * @var $ilTabs ilTabsGUI
		 */
		global $tpl, $ilTabs;

		$ilTabs->activateTab('showContent');

		$form = $this->initCommentForm();
		$form->addCommandButton('insertLearnerComment', $this->lng->txt('insert'));
		$form->addCommandButton('showContent', $this->lng->txt('cancel'));

		$tpl->setContent($form->getHTML());
	}

	/**
	 * 
	 */
	public function insertTutorComment()
	{
		$this->insertComment(1);
	}

	/**
	 * 
	 */
	public function insertLearnerComment()
	{
		$this->insertComment(0);
	}

	/**
	 * @param int $is_tutor
	 */
	private function insertComment($is_tutor = 0)
	{
		$form = $this->initCommentForm();

		if($form->checkInput())
		{
			$this->objComment = new ilObjComment();

			$this->objComment->setObjId($this->object->getId());
			$this->objComment->setCommentText($form->getInput('comment_text'));
			$this->objComment->setInteractive((int)$form->getInput('is_interactive'));

			// calculate seconds
			$comment_time = $form->getInput('comment_time');
			$seconds      = $comment_time['time']['h'] * 3600
				+ $comment_time['time']['m'] * 60
				+ $comment_time['time']['s'];
			$this->objComment->setCommentTime($seconds);
			$this->objComment->setIsTutor($is_tutor);
			$this->objComment->create();

			ilUtil::sendSuccess($this->lng->txt('saved_successfully'));
		}
		else
		{
			// @todo: You left the happy path... Please handle errors, populate the form fields with the correct values and display the form again.
			// @todo: And please pay attention on the correct context (content for public comments, settings for tutor comments)
		}

		// @todo: I don't understand the difference between "postComment" and "insertLearnerComment"
		if($is_tutor)
		{
			$this->editComments();
		}
		else
		{
			$this->showContent();
		}
	}

	/**
	 * 
	 */
	public function editComment()
	{
		/**
		 * @var $tpl    ilTemplate
		 * @var $ilTabs ilTabsGUI
		 */
		global $tpl, $ilTabs;

		$ilTabs->activateTab('editProperties');
		$form = $this->initCommentForm();

		$frm_id = new ilHiddenInputGUI('comment_id');
		$form->addItem($frm_id);

		$form->setFormAction($this->ctrl->getFormAction($this, 'updateComment'));
		// @todo: Untranslated language variable
		$form->setTitle($this->plugin->txt('edit_comment'));

		$form->addCommandButton('updateComment', $this->lng->txt('save'));
		$form->addCommandButton('editProperties', $this->lng->txt('cancel'));

		if(isset($_GET['comment_id']))
		{
			$comment_data             = $this->object->getCommentDataById((int)$_GET['comment_id']);
			$values['comment_id']     = $comment_data['comment_id'];
			$values['comment_time']   = $comment_data['comment_time'];
			$values['comment_text']   = $comment_data['comment_text'];
			$values['is_interactive'] = $comment_data['is_interactive'];

			$form->setValuesByArray($values, true);
		}

		$tpl->setContent($form->getHTML());
	}

	/**
	 * 
	 */
	public function updateComment()
	{
		$form = $this->initCommentForm();
		if($form->checkInput())
		{
			$comment_id = $form->getInput('comment_id');
			if($comment_id > 0)
			{
				$this->objComment = new ilObjComment($comment_id);

			}
			$this->objComment->setCommentText($form->getInput('comment_text'));
			$this->objComment->setInteractive((int)$form->getInput('is_interactive'));

			// calculate seconds
			$comment_time = $form->getInput('comment_time');
			$seconds      = $comment_time['time']['h'] * 3600
				+ $comment_time['time']['m'] * 60
				+ $comment_time['time']['s'];
			$this->objComment->setCommentTime($seconds);
			$this->objComment->update();
			$this->editComments();
		}
		else
		{
			$form->setValuesByPost();
			$this->editComment();
		}
	}

	/**
	 * 
	 */
	public function editProperties()
	{
		/**
		 * @var $tpl    ilTemplate
		 * @var $ilTabs ilTabsGUI
		 */
		global $tpl, $ilTabs;

		$this->setSubTabs('editProperties');

		$ilTabs->activateTab('editProperties');
		$ilTabs->activateSubTab('editProperties');

		$form = $this->initEditForm();

		$values['title']      = $this->object->getTitle();
		$values['desc']       = $this->object->getDescription();
		$values['video_file'] = ilObject::_lookupTitle($this->object->getMobId()); 

		$form->setValuesByArray($values);

		$tpl->setContent($form->getHTML());
	}

	/**
	 * 
	 */
	public function editComments()
	{
		/**
		 * @var $tpl    ilTemplate
		 * @var $ilTabs ilTabsGUI
		 */
		global $tpl, $ilTabs;

		$this->setSubTabs('editProperties');

		$ilTabs->activateTab('editProperties');
		$ilTabs->activateSubTab('editComments');

		$tbl_data = $this->object->getCommentsTableData();
		$this->plugin->includeClass('class.ilInteractiveVideoCommentsTableGUI.php');
		$tbl = new ilInteractiveVideoCommentsTableGUI($this, 'editComments');

		$tbl->setData($tbl_data);
		$tpl->setContent($tbl->getHTML());
	}

	/**
	 * @param string $type
	 * @return array
	 */
	protected function initCreationForms($type)
	{
		return array(
			self::CFORM_NEW => $this->initCreateForm($type)
		);
	}

	/**
	 * @param string $type
	 * @return ilPropertyFormGUI
	 */
	public function  initCreateForm($type)
	{
		$form = parent::initCreateForm($type);

		$upload_field = new ilFileInputGUI($this->plugin->txt('video_file'), 'video_file');
		$upload_field->setSuffixes(array('mp4', 'mov'));
		$upload_field->setRequired(true);
		$form->addItem($upload_field);

		return $form;
	}

	/**
	 * @return ilPropertyFormGUI
	 */
	public function initEditForm()
	{
		$form = parent::initEditForm();

		$upload_field = new ilFileInputGUI($this->plugin->txt('video_file'), 'video_file');
		$upload_field->setSuffixes(array('mp4', 'mov'));
		$form->addItem($upload_field);

		return $form;
	}

	/**
	 * Overwriting this method is necessary to handle creation problems with the api
	 */
	public function save()
	{
		$this->saveObject();
	}

	/**
	 * Overwriting this method is necessary to handle creation problems with the api
	 */
	public function saveObject()
	{
		try
		{
			parent::saveObject();
		}
		catch(Exception $e)
		{
			if($this->plugin->txt($e->getMessage()) != '-' . $e->getMessage() . '-')
			{
				ilUtil::sendFailure($this->plugin->txt($e->getMessage()), true);
			}

			$this->ctrl->setParameterByClass('ilrepositorygui', 'ref_id', (int)$_GET['ref_id']);
			$this->ctrl->redirectByClass('ilrepositorygui');
		}
	}

	/**
	 * @see ilDesktopItemHandling::addToDesk()
	 */
	public function addToDeskObject()
	{
		/**
		 * @var $ilSetting ilSetting
		 */
		global $ilSetting;

		if((int)$ilSetting->get('disable_my_offers'))
		{
			$this->ctrl->redirect($this);
			return;
		}

		include_once './Services/PersonalDesktop/classes/class.ilDesktopItemGUI.php';
		ilDesktopItemGUI::addToDesktop();
		ilUtil::sendSuccess($this->lng->txt('added_to_desktop'), true);
		$this->ctrl->redirect($this);
	}

	/**
	 * @see ilDesktopItemHandling::removeFromDesk()
	 */
	public function removeFromDeskObject()
	{
		global $ilSetting;

		if((int)$ilSetting->get('disable_my_offers'))
		{
			$this->ctrl->redirect($this);
			return;
		}

		include_once './Services/PersonalDesktop/classes/class.ilDesktopItemGUI.php';
		ilDesktopItemGUI::removeFromDesktop();
		ilUtil::sendSuccess($this->lng->txt('removed_from_desktop'), true);
		$this->ctrl->redirect($this);
	}

	/**
	 * @param string $a_sub_type
	 * @param int    $a_sub_id
	 * @return ilObjectListGUI|ilObjInteractiveVideoListGUI
	 */
	protected function initHeaderAction($a_sub_type = null, $a_sub_id = null)
	{
		/**
		 * @var $ilUser ilObjUser
		 */
		global $ilUser;

		$lg = parent::initHeaderAction();

		if($lg instanceof ilObjInteractiveVideoListGUI)
		{
			if($ilUser->getId() != ANONYMOUS_USER_ID)
			{
				// Maybe handle notifications in future ...
			}
		}

		return $lg;
	}

	/**
	 *
	 */
	protected function setTabs()
	{
		/**
		 * @var $ilTabs   ilTabsGUI
		 * @var $ilAccess ilAccessHandler
		 */
		global $ilTabs, $ilAccess;

		if($ilAccess->checkAccess('read', '', $this->object->getRefId()))
		{
			$ilTabs->addTab('content', $this->lng->txt('content'), $this->ctrl->getLinkTarget($this, 'showContent'));
		}

		$this->addInfoTab();

		if($ilAccess->checkAccess('write', '', $this->object->getRefId()))
		{
			$ilTabs->addTab('editProperties', $this->lng->txt('edit'), $this->ctrl->getLinkTarget($this, 'editProperties'));
		}

		$this->addPermissionTab();
	}

	/**
	 * @param string $a_tab
	 */
	public function setSubTabs($a_tab)
	{
		/**
		 * @var $ilTabs   ilTabsGUI
		 */
		global $ilTabs;

		switch($a_tab)
		{
			case 'editProperties':
				$ilTabs->addSubTab('editProperties', $this->lng->txt('settings'),$this->ctrl->getLinkTarget($this,'editProperties'));
				$ilTabs->addSubTab('editComments', $this->plugin->txt('comments'),$this->ctrl->getLinkTarget($this,'editComments'));
				break;
		}
	}
}