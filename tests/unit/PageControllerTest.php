<?php
/**
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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
 */


namespace OCA\UserManagement\Test\Unit;


use OCA\UserManagement\Controller\PageController;
use OCP\App\IAppManager;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\ISubAdminManager;
use OCP\IUser;
use OCP\IUserSession;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class PageControllerTest extends \PHPUnit_Framework_TestCase {

	public function testIndex() {

		/** @var IRequest $request */
		$request = $this->createMock(IRequest::class);
		/** @var IGroupManager | \PHPUnit_Framework_MockObject_MockObject $groupManager */
		$groupManager = $this->createMock(IGroupManager::class);
		/** @var IConfig $config */
		$config = $this->createMock(IConfig::class);
		/** @var IUserSession | \PHPUnit_Framework_MockObject_MockObject $userSession */
		$userSession = $this->createMock(IUserSession::class);
		/** @var IAppManager $appManager */
		$appManager = $this->createMock(IAppManager::class);
		/** @var EventDispatcherInterface $eventDispatcher */
		$eventDispatcher = $this->createMock(EventDispatcherInterface::class);

		$user = $this->createMock(IUser::class);
		$userSession->method('getUser')->willReturn($user);

		$groupManager->method('isAdmin')->willReturn(true);
		$groupManager->method('search')->willReturn([]);
		$subAdmin = $this->createMock(ISubAdminManager::class);
		$subAdmin->method('getAllSubAdmins')->willReturn([]);
		$groupManager->method('getSubAdmin')->willReturn($subAdmin);

		$controller = new PageController('user_management',
			$request,
			$groupManager,
			$config,
			$userSession,
			$appManager,
			$eventDispatcher);
		$response = $controller->index();
		$this->assertInstanceOf(TemplateResponse::class, $response);
	}
}