<?php
/**
 * ownCloud
 *
 * @author Artur Neumann <artur@jankaritech.com>
 * @copyright Copyright (c) 2017 Artur Neumann artur@jankaritech.com
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License,
 * as published by the Free Software Foundation;
 * either version 3 of the License, or any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace Page;

use Behat\Mink\Element\NodeElement;
use Behat\Mink\Session;
use Page\UserPageElement\GroupList;
use SensioLabs\Behat\PageObjectExtension\PageObject\Exception\ElementNotFoundException;
use WebDriver\Exception\NoSuchElement;

/**
 * Users page.
 */
class UsersPage extends OwncloudPage {

	/**
	 *
	 * @var string $path
	 */
	protected $path = '/index.php/apps/user_management';

	protected $userTrXpath = ".//table[@id='userlist']/tbody/tr";
	protected $quotaSelectXpath = ".//select[@class='quota-user']";
	protected $quotaOptionXpath = "//option[contains(text(), '%s')]";
	protected $manualQuotaInputXpath = "//input[contains(@data-original-title,'Please enter storage quota')]";
	protected $settingsBtnXpath = ".//*[@id='app-settings-header']/button";
	protected $settingContentId = "app-settings-content";
	protected $labelMailOnUserCreateXpath = ".//label[@for='CheckboxMailOnUserCreate']";
	protected $settingByTextXpath = ".//*[@id='userlistoptions']//label[normalize-space()='%s']";
	protected $newUserUsernameFieldId = "newusername";
	protected $newUserPasswordFieldId = "newuserpassword";
	protected $newUserEmailFieldId = "newemail";
	protected $createUserBtnXpath = ".//*[@id='newuser']/input[@type='submit']";
	protected $newUserGroupsDropDownXpath = ".//*[@id='newuser']//div[@class='groupsListContainer multiselect button']";
	protected $newUserGroupsDropDownListTag = "li";
	protected $newUserGroupsSelectedClass = "selected";
	protected $newUserGroupsListXpath = ".//*[@id='newuser']//ul[@class='multiselectoptions down']";
	protected $newUserGroupXpath = ".//*[@id='newuser']//ul[@class='multiselectoptions down']//label[@title='%s']/..";
	protected $newUserAddGroupBtnXpath = ".//*[@id='newuser']//ul[@class='multiselectoptions down']//li[@title='add group']";
	protected $createGroupWithNewUserInputXpath = ".//*[@id='newuser']//ul[@class='multiselectoptions down']//input[@type='text']";
	protected $groupListId = "usergrouplist";
	protected $disableUserCheckboxXpath            = "//input[@type='checkbox']";
	/**
	 * @param string $username
	 *
	 * @return NodeElement for the requested user in the table
	 * @throws \Exception
	 */
	public function findUserInTable($username) {
		$userTrs = $this->findAll('xpath', $this->userTrXpath);

		foreach ($userTrs as $userTr) {
			$user = $userTr->find("css", ".name");
			if ($this->getTrimmedText($user) === $username) {
				return $userTr;
			}
		}
		throw new \Exception("Could not find user '$username'");
	}

	/**
	 * @param string $username
	 *
	 * @throws ElementNotFoundException
	 * @return string text describing the quota
	 */
	public function getQuotaOfUser($username) {
		$userTr = $this->findUserInTable($username);
		$selectField = $userTr->find('xpath', $this->quotaSelectXpath);

		if (is_null($selectField)) {
			throw new ElementNotFoundException(
				__METHOD__ .
				" xpath $this->quotaSelectXpath " .
				"could not find quota select element"
			);
		}

		$xpathLocator = "//option[@value='" . $selectField->getValue() . "']";
		$selectField = $selectField->find('xpath', $xpathLocator);

		if (is_null($selectField)) {
			throw new ElementNotFoundException(
				__METHOD__ .
				" xpath $xpathLocator " .
				"could not find quota element"
			);
		}

		return $this->getTrimmedText($selectField);
	}

	/**
	 * Open the settings menu
	 *
	 * @throws ElementNotFoundException
	 * @return void
	 */
	public function openSettingsMenu() {
		$settingsBtn = $this->find("xpath", $this->settingsBtnXpath);
		if (is_null($settingsBtn)) {
			throw new ElementNotFoundException(
				__METHOD__ .
				" xpath $this->settingsBtnXpath " .
				"could not find settings button"
			);
		}
		$settingsBtn->click();
	}

	/**
	 * sets a setting in the settings menu
	 *
	 * @param string $setting the human readable setting string
	 * @param boolean $value
	 *
	 * @throws ElementNotFoundException
	 * @return void
	 */
	public function setSetting($setting, $value = true) {
		$settingContent = $this->findById($this->settingContentId);
		if (is_null($settingContent)) {
			throw new ElementNotFoundException(
				__METHOD__ .
				" id $this->settingContentId " .
				"could not find setting content"
			);
		}

		try {
			$settingContentIsVisible = $settingContent->isVisible();
		} catch (NoSuchElement $e) {
			// Somehow on Edge this can throw NoSuchElement even though
			// we just found the element.
			// TODO: Edge - if it keeps happening then find out why.
			error_log(
				__METHOD__
				. " NoSuchElement while doing settingContent->isVisible()"
				. "\n-------------------------\n"
				. $e->getMessage()
				. "\n-------------------------\n"
			);
			$settingContentIsVisible = false;
		}

		if (!$settingContentIsVisible) {
			$this->openSettingsMenu();
		}

		$xpathLocator = sprintf($this->settingByTextXpath, $setting);
		$settingLabel = $this->find("xpath", $xpathLocator);
		if (is_null($settingLabel)) {
			throw new ElementNotFoundException(
				__METHOD__ .
				" xpath $xpathLocator " .
				"could not find setting '" . $setting . "'"
			);
		}
		//the checkbox is not visible, but we need it to find the status
		$checkBoxId = $settingLabel->getAttribute("for");
		$checkBox = $this->findById($checkBoxId);
		if (is_null($checkBox)) {
			throw new ElementNotFoundException(
				__METHOD__ .
				" could not find checkbox with the id '" . $checkBoxId . "'"
			);
		}
		if ($checkBox->isChecked() !== $value) {
			$settingLabel->click();
		}
	}

	/**
	 * creates a user and adds it to the required groups
	 * if group does not exist it will be created
	 *
	 * @param Session $session
	 * @param string $username
	 * @param string $password
	 * @param string $email
	 * @param string[] $groups
	 *
	 * @throws ElementNotFoundException
	 * @return void
	 */
	public function createUser(
		Session $session, $username, $password, $email = null, $groups = null
	) {
		$this->fillField($this->newUserUsernameFieldId, $username);
		$this->fillField($this->newUserPasswordFieldId, $password);
		$this->setSetting("Send email to new user", !is_null($email));
		if (!is_null($email)) {
			$this->fillField($this->newUserEmailFieldId, $email);
		}
		$createUserBtn = $this->find("xpath", $this->createUserBtnXpath);
		if (is_null($createUserBtn)) {
			throw new ElementNotFoundException(
				__METHOD__ .
				" xpath $this->createUserBtnXpath " .
				"could not find create user button"
			);
		}
		$newUserGroupsDropDown = $this->find(
			"xpath", $this->newUserGroupsDropDownXpath
		);
		if (is_null($newUserGroupsDropDown)) {
			throw new ElementNotFoundException(
				__METHOD__ .
				" xpath $this->newUserGroupsDropDownXpath " .
				"could not find groups dropdown for new user"
			);
		}
		$newUserGroupsDropDown->click();
		$groupDropDownList = $this->find("xpath", $this->newUserGroupsListXpath);
		if (is_null($groupDropDownList)) {
			throw new ElementNotFoundException(
				__METHOD__ .
				" xpath $this->newUserGroupsListXpath " .
				"could not find groups dropdown list"
			);
		}
		$groupsInDropDown = $groupDropDownList->findAll(
			"xpath", $this->newUserGroupsDropDownListTag
		);

		//uncheck all selected groups
		foreach ($groupsInDropDown as $groupLi) {
			if ($groupLi->getAttribute("class") === $this->newUserGroupsSelectedClass) {
				$groupLi->click();
			}
		}

		//now select all groups that we need to have
		if (is_array($groups)) {
			foreach ($groups as $group) {
				$groupItem = $this->find(
					"xpath", sprintf($this->newUserGroupXpath, $group)
				);
				if (!is_null($groupItem)) {
					$groupItem->click();
				} else {
					$newUserAddGroupBtn = $this->find(
						"xpath", $this->newUserAddGroupBtnXpath
					);
					if (is_null($newUserAddGroupBtn)) {
						throw new ElementNotFoundException(
							__METHOD__ .
							" xpath $this->newUserAddGroupBtnXpath " .
							"could not find add-group button while creating a new user"
						);
					}
					$newUserAddGroupBtn->click();
					$createUserInput = $this->find(
						"xpath", $this->createGroupWithNewUserInputXpath
					);
					if (is_null($createUserInput)) {
						throw new ElementNotFoundException(
							__METHOD__ .
							" xpath $this->createGroupWithNewUserInputXpath " .
							"could not find add-group input while creating a new user"
						);
					}
					try {
						$createUserInput->setValue($group . "\n");
					} catch (NoSuchElement $e) {
						// this seems to be a bug in MinkSelenium2Driver.
						// Actually all that we need does happen, so we just don't do anything
					}
				}
			}
		}

		$createUserBtn->click();
		$this->waitForAjaxCallsToStartAndFinish($session);
	}

	/**
	 * @param string $username
	 * @param string $quota text form of quota to be input
	 * @param Session $session
	 *
	 * @throws ElementNotFoundException
	 * @return void
	 */
	public function setQuotaOfUserTo($username, $quota, Session $session) {
		$userTr = $this->findUserInTable($username);
		$selectField = $userTr->find('xpath', $this->quotaSelectXpath);

		if (is_null($selectField)) {
			throw new ElementNotFoundException(
				__METHOD__ .
				" xpath $this->quotaSelectXpath " .
				"could not find quota select element"
			);
		}

		$selectOption = $selectField->find(
			'xpath', sprintf($this->quotaOptionXpath, $quota)
		);
		if (is_null($selectOption)) {
			$xpathLocator = sprintf($this->quotaOptionXpath, "Other");
			$selectOption = $selectField->find('xpath', $xpathLocator);

			if (is_null($selectOption)) {
				throw new ElementNotFoundException(
					__METHOD__ .
					" xpath $xpathLocator " .
					"could not find quota option element"
				);
			}

			$selectOption->click();
			$manualQuotaInputElement = $this->find('xpath', $this->manualQuotaInputXpath);

			if (is_null($manualQuotaInputElement)) {
				throw new ElementNotFoundException(
					__METHOD__ .
					" xpath $this->manualQuotaInputXpath " .
					"could not find manual quota input element"
				);
			}

			$manualQuotaInputElement->setValue($quota);
		} else {
			$selectOption->click();
		}
		$this->waitForOutstandingAjaxCalls($session);
	}

	/**
	 *
	 * @throws ElementNotFoundException
	 * @return GroupList
	 */
	private function getGroupListElement() {
		$groupListElement = $this->findById($this->groupListId);
		if (is_null($groupListElement)) {
			throw new ElementNotFoundException(
				__METHOD__ .
				" id $this->groupListId " .
				"could not find group list element"
			);
		}

		/**
		 *
		 * @var GroupList $groupList
		 */
		$groupList = $this->getPage("UserPageElement\\GroupList");
		$groupList->setElement($groupListElement);
		return $groupList;
	}

	/**
	 * returns all group names as an array
	 *
	 * @return string[]
	 */
	public function getAllGroups() {
		$groupList = $this->getGroupListElement();
		return $groupList->namesToArray();
	}

	/**
	 *
	 * @param string $name
	 * @param Session $session
	 *
	 * @return void
	 */
	public function deleteGroup($name, Session $session) {
		$groupList = $this->getGroupListElement();
		$groupList->deleteGroup($name);
		$this->waitForAjaxCallsToStartAndFinish($session);
	}
	/**
	 * 
	 * @param string $username
	 * 
	 * @return void
	 */
	public function disableUser($username) {
		$userTr = $this->findUserInTable($username);
		$userTr->find("xpath", $this->disableUserCheckboxXpath)->click();
	}

	/** 
	 * 
	 * @param string $groupName
	 * @param Session $session
	 *
	 * @return void
	 */
	public function addGroup($groupName, Session $session) {
		$groupList = $this->getGroupListElement();
		$groupList->addGroup($groupName);
		$this->waitForAjaxCallsToStartAndFinish($session);
	}
}