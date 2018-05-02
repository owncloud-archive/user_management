<?php
/**
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christopher Schäpers <kondou@ts.unde.re>
 * @author Christoph Wurst <christoph@owncloud.com>
 * @author Clark Tomlinson <fallen013@gmail.com>
 * @author cmeh <cmeh@users.noreply.github.com>
 * @author Florin Peter <github@florin-peter.de>
 * @author Jakob Sack <mail@jakobsack.de>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Robin Appelman <icewind@owncloud.com>
 * @author Sam Tuke <mail@samtuke.com>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Ujjwal Bhardwaj <ujjwalb1996@gmail.com>
 * @author Yarno Boelens <yarnoboelens@gmail.com>
 *
 * @copyright Copyright (c) 2018, ownCloud GmbH
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */
namespace OCA\UserManagement\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Mail\IMailer;

class ChangePasswordController extends Controller {

	/** @var IL10N */
	private $l10n;
	/** @var IUserSession */
	private $userSession;
	/** @var IUserManager */
	private $userManager;
	/** @var IGroupManager */
	private $groupManager;
	/** @var IMailer */
	private $mailer;

	/**
	 * ChangePasswordController constructor.
	 *
	 * @param string $appName
	 * @param IRequest $request
	 * @param IL10N $l10n
	 * @param IUserSession $userSession
	 * @param IUserManager $userManager
	 * @param IGroupManager $groupManager
	 */
	public function __construct($appName,
								IRequest $request,
								IL10N $l10n,
								IUserSession $userSession,
								IUserManager $userManager,
								IGroupManager $groupManager,
								IMailer $mailer
	) {
		parent::__construct($appName, $request);
		$this->l10n = $l10n;
		$this->userSession = $userSession;
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
		$this->mailer = $mailer;
	}

	/**
	 * @param string $userId
	 * @param string $password
	 * @param string $recoveryPassword
	 * @return JSONResponse
	 * @throws \Exception
	 */
	public function changePassword($userId, $password = null, $recoveryPassword = null) {

		if ($userId === null) {
			return new JSONResponse([
				'status' => 'error',
				'data' => ['message' => $this->l10n->t('No user id supplied')]]);
		}

		$isUserAccessible = false;
		$currentUserObject = $this->userSession->getUser();
		$targetUserObject = $this->userManager->getByUserId($userId);
		if($currentUserObject !== null && $targetUserObject !== null) {
			$isUserAccessible = $this->groupManager->getSubAdmin()->isUserAccessible($currentUserObject, $targetUserObject);
		}

		if (!$this->groupManager->isAdmin($currentUserObject->getUserId()) &&
			!$isUserAccessible ) {
			return new JSONResponse([
				'status' => 'error',
				'data' => ['message' => $this->l10n->t('Authentication error')]]);
		}

		// @codeCoverageIgnoreStart
		if (\OC_App::isEnabled('encryption')) {
			//handle the recovery case
			$crypt = new \OCA\Encryption\Crypto\Crypt(
				\OC::$server->getLogger(),
				\OC::$server->getUserSession(),
				\OC::$server->getConfig(),
				\OC::$server->getL10N('encryption'));
			$keyStorage = \OC::$server->getEncryptionKeyStorage();
			$util = new \OCA\Encryption\Util(
				new \OC\Files\View(),
				$crypt,
				\OC::$server->getLogger(),
				\OC::$server->getUserSession(),
				\OC::$server->getConfig(),
				\OC::$server->getUserManager());
			$keyManager = new \OCA\Encryption\KeyManager(
				$keyStorage,
				$crypt,
				\OC::$server->getConfig(),
				\OC::$server->getUserSession(),
				new \OCA\Encryption\Session(\OC::$server->getSession()),
				\OC::$server->getLogger(),
				$util);
			$recovery = new \OCA\Encryption\Recovery(
				\OC::$server->getUserSession(),
				$crypt,
				\OC::$server->getSecureRandom(),
				$keyManager,
				\OC::$server->getConfig(),
				$keyStorage,
				\OC::$server->getEncryptionFilesHelper(),
				new \OC\Files\View());
			$recoveryAdminEnabled = $recovery->isRecoveryKeyEnabled();

			$validRecoveryPassword = false;
			$recoveryEnabledForUser = false;
			if ($recoveryAdminEnabled) {
				$validRecoveryPassword = $keyManager->checkRecoveryPassword($recoveryPassword);
				$recoveryEnabledForUser = $recovery->isRecoveryEnabledForUser($userId);
			}

			if ($recoveryEnabledForUser && $recoveryPassword === '') {
				return new JSONResponse([
					'status' => 'error',
					'data' => ['message' => $this->l10n->t('Please provide an admin recovery password; otherwise, all user data will be lost.')]]);
			}

			if ($recoveryEnabledForUser && ! $validRecoveryPassword) {
				return new JSONResponse([
					'status' => 'error',
					'data' => ['message' => $this->l10n->t('Wrong admin recovery password. Please check the password and try again.')]]);
			}

			// now we know that everything is fine regarding the recovery password, let's try to change the password
			$result = $targetUserObject->setPassword($password, $recoveryPassword);
			if (!$result && $recoveryEnabledForUser) {
				return new JSONResponse([
					'status' => 'error',
					'data' => ['message' => $this->l10n->t('Backend doesn\'t support password change, but the user\'s encryption key was successfully updated.')]]);
			}

			if (!$result && !$recoveryEnabledForUser) {
				return new JSONResponse([
					'status' => 'error',
					'data' => ['message' => $this->l10n->t('Unable to change password')]]);
			}

			$this->sendNotificationMail($userId);
			return new JSONResponse([
				'status' => 'success',
				'data' => ['userId' => $userId]]);
		}
		// @codeCoverageIgnoreEnd

		// if encryption is disabled, proceed
		try {
			if ($password !== null && $targetUserObject->setPassword($password)) {
				$this->sendNotificationMail($userId);
				return new JSONResponse([
					'status' => 'success',
					'data' => ['userId' => $userId]]);
			}

			return new JSONResponse([
				'status' => 'error',
				'data' => ['message' => $this->l10n->t('Unable to change password')]]);
		} catch (\Exception $e) {
			return new JSONResponse([
				'status' => 'error',
				'data' => ['message' => $e->getMessage()]]);
		}
	}

	private function sendNotificationMail($userId) {
		$user = $this->userManager->getByUserId($userId);
		$email = $user->getEMailAddress();
		$defaults = new \OC_Defaults();
		$from = \OCP\Util::getDefaultEmailAddress('lostpassword-noreply');

		if ($email !== null && $email !== '') {
			$tmpl = new \OC_Template('core', 'lostpassword/notify');
			$msg = $tmpl->fetchPage();

			try {
				$message = $this->mailer->createMessage();
				$message->setTo([$email => $user->getUserName()]);
				$message->setSubject($this->l10n->t('%s password changed successfully', [$defaults->getName()]));
				$message->setPlainBody($msg);
				$message->setFrom([$from => $defaults->getName()]);
				$this->mailer->send($message);
			} catch (\Exception $e) {
				throw new \Exception($this->l10n->t(
					'Couldn\'t send reset email. Please contact your administrator.'
				));
			}
		}
	}
}
