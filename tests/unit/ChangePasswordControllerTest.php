<?php
/**
 * Created by PhpStorm.
 * User: deepdiver
 * Date: 12/03/18
 * Time: 13:13
 */

namespace OCA\UserManagement\Test\Unit;

use OC\L10N\L10NString;
use OC\Mail\Message;
use OCA\UserManagement\Controller\ChangePasswordController;
use OCP\IConfig;
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
	/** @var  IRequest | \PHPUnit_Framework_MockObject_MockObject */
	private $request;
	/** @var  IL10N | \PHPUnit_Framework_MockObject_MockObject */
	private $l10n;
	/** @var  IUserSession | \PHPUnit_Framework_MockObject_MockObject */
	private $userSession;
	/** @var  IUserManager | \PHPUnit_Framework_MockObject_MockObject */
	private $userManager;
	/** @var  IGroupManager | \PHPUnit_Framework_MockObject_MockObject */
	private $groupManager;
	/** @var  IMailer | \PHPUnit_Framework_MockObject_MockObject */
	private $mailer;
	/** @var  ChangePasswordController */
	private $controller;
	protected function setUp() {
		$this->request = $this->createMock(IRequest::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->mailer = $this->createMock(IMailer::class);

		$this->controller = new ChangePasswordController('user_management',
			$this->request, $this->l10n, $this->userSession, $this->userManager,
			$this->groupManager, $this->mailer);
		parent::setUp();
	}

	/**
	 * @dataProvider providesData
	 */
	public function testPasswordChange($expectedStatus, $isAdmin, $isSubAdmin, $passwordChangeIsSuccessfull = true) {
		$sessionUser = $this->createMock(IUser::class);
		$sessionUser->method('getUID')->willReturn('admin');

		$targetUser = $this->createMock(IUser::class);
		$targetUser->method('setPassword')->willReturn($passwordChangeIsSuccessfull);
		$targetUser->method('getEMailAddress')->willReturn('alice@example.net');

		$subAdmin = $this->createMock(ISubAdminManager::class);
		$subAdmin->method('isUserAccessible')->willReturn($isSubAdmin);

		$message = $this->createMock(Message::class);
		$this->mailer->method('createMessage')->willReturn($message);

		$this->userSession->method('getUser')->willReturn($sessionUser);
		$this->userManager->method('get')->willReturn($targetUser);
		$this->groupManager->method('isAdmin')->willReturn($isAdmin);
		$this->groupManager->method('getSubAdmin')->willReturn($subAdmin);

		$response = $this->controller->changePassword('alice', '123456');
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
