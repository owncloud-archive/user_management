<?php
/**
 * Created by PhpStorm.
 * User: deepdiver
 * Date: 12/03/18
 * Time: 13:13
 */

namespace OCA\UserManagement\Test\Unit;

use OC\Mail\Message;
use OCA\UserManagement\Controller\ChangePasswordController;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\ISubAdminManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Mail\IMailer;
use Test\TestCase;

class ChangePasswordControllerTest extends TestCase {

	/**
	 * @dataProvider providesData
	 */
	public function testPasswordChange($expectedStatus, $isAdmin, $isSubAdmin, $passwordChangeIsSuccessfull = true) {
		$request = $this->createMock(IRequest::class);
		$l10n = $this->createMock(IL10N::class);
		$userSession = $this->createMock(IUserSession::class);
		$userManager = $this->createMock(IUserManager::class);
		$groupManager = $this->createMock(IGroupManager::class);

		$sessionUser = $this->createMock(IUser::class);
		$sessionUser->method('getUID')->willReturn('admin');

		$targetUser = $this->createMock(IUser::class);
		$targetUser->method('setPassword')->willReturn($passwordChangeIsSuccessfull);
		$targetUser->method('getEMailAddress')->willReturn('alice@example.net');

		$subAdmin = $this->createMock(ISubAdminManager::class);
		$subAdmin->method('isUserAccessible')->willReturn($isSubAdmin);

		$message = $this->createMock(Message::class);
		$mailer = $this->createMock(IMailer::class);
		$mailer->method('createMessage')->willReturn($message);
//		$mailer->method('send')->willReturn(true);

		$userSession->method('getUser')->willReturn($sessionUser);
		$userManager->method('get')->willReturn($targetUser);
		$groupManager->method('isAdmin')->willReturn($isAdmin);
		$groupManager->method('getSubAdmin')->willReturn($subAdmin);

		$controller = new ChangePasswordController('user_management',
			$request, $l10n, $userSession, $userManager, $groupManager, $mailer);

		$response = $controller->changePassword('alice', '123456');
		$this->assertEquals($expectedStatus, $response->getData()['status']);
	}

	public function providesData() {
		return [
			'success' => ['success', true, false],
			'sub admin' => ['success', false, true],
			'no admin, no subadmin' => ['error', false, false],
			'password change fails' => ['error', true, false, false]
		];
	}
}
