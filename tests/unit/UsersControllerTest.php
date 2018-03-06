<?php
/**
 * @author Lukas Reschke
 * @copyright Copyright (c) 2014-2015 Lukas Reschke lukas@owncloud.com
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OCA\UserManagement\Test\Unit;

use OCA\UserManagement\Controller\UsersController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\Defaults;
use \OCP\IUser;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IGroupManager;
use OCP\IUserSession;
use OCP\IConfig;
use OCP\Security\ISecureRandom;
use OCP\IL10N;
use OCP\ILogger;
use OCP\Mail\IMailer;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IURLGenerator;
use OCP\App\IAppManager;
use OCP\IAvatarManager;
use OC\SubAdmin;
use OC\Mail\Message;
use OC\User\User;
use OC\Group\Group;
use OCP\IGroup;
use Test\TestCase;
use Test\Util\User\Dummy;
use OCP\IAvatar;
use OC\User\Session;
use OC\Group\Manager;


/**
 * @group DB
 *
 * @package Tests\Settings\Controller
 */
class UsersControllerTest extends TestCase {

	/** @var IGroupManager | \PHPUnit_Framework_MockObject_MockObject*/
	private $groupManager;
	/** @var IUserSession | \PHPUnit_Framework_MockObject_MockObject */
	private $userSession;
	/** @var IL10N | \PHPUnit_Framework_MockObject_MockObject */
	private $l10N;
	/** @var IAvatarManager | \PHPUnit_Framework_MockObject_MockObject */
	private $avatarManager;
	/** @var IConfig | \PHPUnit_Framework_MockObject_MockObject */
	private $config;
	/** @var IUserManager | \PHPUnit_Framework_MockObject_MockObject */
	private $userManager;
	/** @var ISecureRandom | \PHPUnit_Framework_MockObject_MockObject */
	private $secureRandom;
	/** @var ILogger | \PHPUnit_Framework_MockObject_MockObject */
	private $logger;
	/** @var Defaults | \PHPUnit_Framework_MockObject_MockObject */
	private $defaults;
	/** @var ITimeFactory | \PHPUnit_Framework_MockObject_MockObject */
	private $timeFactory;
	/** @var IMailer | \PHPUnit_Framework_MockObject_MockObject */
	private $mailer;
	/** @var IURLGenerator | \PHPUnit_Framework_MockObject_MockObject */
	private $urlGenerator;
	/** @var IAppManager | \PHPUnit_Framework_MockObject_MockObject */
	private $appManager;
	/** @var IRequest | \PHPUnit_Framework_MockObject_MockObject */
	private $request;

	protected function setUp() {
		$this->groupManager = $this->getMockBuilder(Manager::class)
			->disableOriginalConstructor()->getMock();
		$this->userManager = $this->getMockBuilder(IUserManager::class)
			->disableOriginalConstructor()->getMock();
		$this->userSession = $this->getMockBuilder(Session::class)
			->disableOriginalConstructor()->getMock();
		$this->l10N = $this->getMockBuilder(IL10N::class)
			->disableOriginalConstructor()->getMock();
		$this->config = $this->getMockBuilder(IConfig::class)
			->disableOriginalConstructor()->getMock();
		$this->l10N
			->expects($this->any())
			->method('t')
			->will($this->returnCallback(function($text, $parameters = []) {
				return vsprintf($text, $parameters);
			}));
		$this->defaults = $this->getMockBuilder(\OC_Defaults::class)
			->disableOriginalConstructor()->getMock();
		$this->mailer = $this->createMock(IMailer::class);
//		$this->container['DefaultMailAddress'] = 'no-reply@owncloud.com';
		$this->logger = $this->getMockBuilder(ILogger::class)
			->disableOriginalConstructor()->getMock();
		$this->urlGenerator = $this->getMockBuilder(IURLGenerator::class)
			->disableOriginalConstructor()->getMock();
		$this->appManager = $this->getMockBuilder(IAppManager::class)
			->disableOriginalConstructor()->getMock();
		$this->secureRandom = $this->getMockBuilder(ISecureRandom::class)
			->disableOriginalConstructor()->getMock();
		$this->timeFactory = $this->getMockBuilder(ITimeFactory::class)
			->disableOriginalConstructor()->getMock();
		$this->existingUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()->getMock();

		/*
		 * Set default avatar behaviour for whole test suite
		 */
		$this->avatarManager = $this->createMock(IAvatarManager::class);

		$avatarExists = $this->createMock(IAvatar::class);
		$avatarExists->method('exists')->willReturn(true);
		$avatarNotExists = $this->createMock(IAvatar::class);
		$avatarNotExists->method('exists')->willReturn(false);
		$this->avatarManager
			->method('getAvatar')
			->will($this->returnValueMap([
				['foo', $avatarExists],
				['bar', $avatarExists],
				['admin', $avatarNotExists],
			]));

		$this->config
			->method('getSystemValue')
			->with('enable_avatars', true)
			->willReturn(true);

		$this->request = $this->createMock(IRequest::class);
	}

	public function testIndexAdmin() {
		$user = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()->getMock();
		$user->expects($this->any())
			->method('getUID')
			->will($this->returnValue('user'));
		$this->userSession
			->expects($this->any())
			->method('getUser')
			->will($this->returnValue($user));
		$this->groupManager
			->expects($this->any())
			->method('isAdmin')
			->will($this->returnValue(true));

		$foo = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$foo
			->expects($this->exactly(2))
			->method('getUID')
			->will($this->returnValue('foo'));
		$foo
			->expects($this->once())
			->method('getDisplayName')
			->will($this->returnValue('M. Foo'));
		$foo
			->expects($this->once())
			->method('getEMailAddress')
			->will($this->returnValue('foo@bar.com'));
		$foo
			->expects($this->once())
			->method('isEnabled')
			->will($this->returnValue(true));
		$foo
			->expects($this->once())
			->method('getQuota')
			->will($this->returnValue('1024'));
		$foo
			->method('getLastLogin')
			->will($this->returnValue(500));
		$foo
			->method('getHome')
			->will($this->returnValue('/home/foo'));
		$foo
			->expects($this->once())
			->method('getBackendClassName')
			->will($this->returnValue('OC_User_Database'));
		$admin = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$admin
			->expects($this->exactly(2))
			->method('getUID')
			->will($this->returnValue('admin'));
		$admin
			->expects($this->once())
			->method('getDisplayName')
			->will($this->returnValue('S. Admin'));
		$admin
			->expects($this->once())
			->method('getEMailAddress')
			->will($this->returnValue('admin@bar.com'));
		$admin
			->expects($this->once())
			->method('isEnabled')
			->will($this->returnValue(true));
		$admin
			->expects($this->once())
			->method('getQuota')
			->will($this->returnValue('404'));
		$admin
			->expects($this->once())
			->method('getLastLogin')
			->will($this->returnValue(12));
		$admin
			->expects($this->once())
			->method('getHome')
			->will($this->returnValue('/home/admin'));
		$admin
			->expects($this->once())
			->method('getBackendClassName')
			->will($this->returnValue(Dummy::class));
		$bar = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$bar
			->expects($this->exactly(2))
			->method('getUID')
			->will($this->returnValue('bar'));
		$bar
			->expects($this->once())
			->method('getDisplayName')
			->will($this->returnValue('B. Ar'));
		$bar
			->expects($this->once())
			->method('getEMailAddress')
			->will($this->returnValue('bar@dummy.com'));
		$bar
			->expects($this->once())
			->method('isEnabled')
			->will($this->returnValue(false));
		$bar
			->expects($this->once())
			->method('getQuota')
			->will($this->returnValue('2323'));
		$bar
			->method('getLastLogin')
			->will($this->returnValue(3999));
		$bar
			->method('getHome')
			->will($this->returnValue('/home/bar'));
		$bar
			->expects($this->once())
			->method('getBackendClassName')
			->will($this->returnValue(Dummy::class));

		$this->groupManager
			->expects($this->once())
			->method('displayNamesInGroup')
			->with('gid', 'pattern')
			->will($this->returnValue(['foo' => 'M. Foo', 'admin' => 'S. Admin', 'bar' => 'B. Ar']));
		$this->groupManager
			->expects($this->exactly(3))
			->method('getUserGroupIds')
			->will($this->onConsecutiveCalls(['Users', 'Support'], ['admins', 'Support'], ['External Users']));
		$this->userManager
			->expects($this->at(0))
			->method('get')
			->with('foo')
			->will($this->returnValue($foo));
		$this->userManager
			->expects($this->at(1))
			->method('get')
			->with('admin')
			->will($this->returnValue($admin));
		$this->userManager
			->expects($this->at(2))
			->method('get')
			->with('bar')
			->will($this->returnValue($bar));

		$subadmin = $this->getMockBuilder(SubAdmin::class)
			->disableOriginalConstructor()
			->getMock();
		$subadmin
			->expects($this->any())
			->method('getSubAdminsGroups')
			->with($foo)
			->will($this->returnValue([]));
		$subadmin
			->expects($this->any())
			->method('getSubAdminsGroups')
			->with($admin)
			->will($this->returnValue([]));
		$subadmin
			->expects($this->any())
			->method('getSubAdminsGroups')
			->with($bar)
			->will($this->returnValue([]));
		$this->groupManager
			->expects($this->any())
			->method('getSubAdmin')
			->will($this->returnValue($subadmin));

		$expectedResponse = new DataResponse(
			[
				0 => [
					'name' => 'foo',
					'displayname' => 'M. Foo',
					'groups' => ['Users', 'Support'],
					'subadmin' => [],
					'isEnabled' => true,
					'quota' => 1024,
					'storageLocation' => '/home/foo',
					'lastLogin' => 500000,
					'backend' => 'OC_User_Database',
					'email' => 'foo@bar.com',
					'isRestoreDisabled' => false,
					'isAvatarAvailable' => true,
				],
				1 => [
					'name' => 'admin',
					'displayname' => 'S. Admin',
					'groups' => ['admins', 'Support'],
					'subadmin' => [],
					'isEnabled' => true,
					'quota' => 404,
					'storageLocation' => '/home/admin',
					'lastLogin' => 12000,
					'backend' => Dummy::class,
					'email' => 'admin@bar.com',
					'isRestoreDisabled' => false,
					'isAvatarAvailable' => false,
				],
				2 => [
					'name' => 'bar',
					'displayname' => 'B. Ar',
					'groups' => ['External Users'],
					'subadmin' => [],
					'isEnabled' => false,
					'quota' => 2323,
					'storageLocation' => '/home/bar',
					'lastLogin' => 3999000,
					'backend' => Dummy::class,
					'email' => 'bar@dummy.com',
					'isRestoreDisabled' => false,
					'isAvatarAvailable' => true,
				],
			]
		);

		$response = $this->createController()->index(0, 10, 'gid', 'pattern');
		$this->assertEquals($expectedResponse, $response);
	}

	public function testIndexSubAdmin() {
		$this->groupManager
			->expects($this->any())
			->method('isAdmin')
			->will($this->returnValue(false));

		$user = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$user->expects($this->any())
			->method('getUID')
			->will($this->returnValue('user'));
		$this->userSession
			->expects($this->any())
			->method('getUser')
			->will($this->returnValue($user));

		$foo = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$foo
			->expects($this->exactly(2))
			->method('getUID')
			->will($this->returnValue('foo'));
		$foo
			->expects($this->once())
			->method('getDisplayName')
			->will($this->returnValue('M. Foo'));
		$foo
			->expects($this->once())
			->method('getEMailAddress')
			->will($this->returnValue('foo@bar.com'));
		$foo
			->expects($this->once())
			->method('isEnabled')
			->will($this->returnValue(true));
		$foo
			->expects($this->once())
			->method('getQuota')
			->will($this->returnValue('1024'));
		$foo
			->method('getLastLogin')
			->will($this->returnValue(500));
		$foo
			->method('getHome')
			->will($this->returnValue('/home/foo'));
		$foo
			->expects($this->once())
			->method('getBackendClassName')
			->will($this->returnValue('OC_User_Database'));
		$admin = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$admin
			->expects($this->exactly(2))
			->method('getUID')
			->will($this->returnValue('admin'));
		$admin
			->expects($this->once())
			->method('getDisplayName')
			->will($this->returnValue('S. Admin'));
		$admin
			->expects($this->once())
			->method('getEMailAddress')
			->will($this->returnValue('admin@bar.com'));
		$admin
			->expects($this->once())
			->method('isEnabled')
			->will($this->returnValue(true));
		$admin
			->expects($this->once())
			->method('getQuota')
			->will($this->returnValue('404'));
		$admin
			->expects($this->once())
			->method('getLastLogin')
			->will($this->returnValue(12));
		$admin
			->expects($this->once())
			->method('getHome')
			->will($this->returnValue('/home/admin'));
		$admin
			->expects($this->once())
			->method('getBackendClassName')
			->will($this->returnValue(Dummy::class));
		$bar = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$bar
			->expects($this->exactly(2))
			->method('getUID')
			->will($this->returnValue('bar'));
		$bar
			->expects($this->once())
			->method('getDisplayName')
			->will($this->returnValue('B. Ar'));
		$bar
			->expects($this->once())
			->method('getEMailAddress')
			->will($this->returnValue('bar@dummy.com'));
		$bar
			->expects($this->once())
			->method('isEnabled')
			->will($this->returnValue(false));
		$bar
			->expects($this->once())
			->method('getQuota')
			->will($this->returnValue('2323'));
		$bar
			->method('getLastLogin')
			->will($this->returnValue(3999));
		$bar
			->method('getHome')
			->will($this->returnValue('/home/bar'));
		$bar
			->expects($this->once())
			->method('getBackendClassName')
			->will($this->returnValue(Dummy::class));

		$this->groupManager
			->expects($this->at(2))
			->method('displayNamesInGroup')
			->with('SubGroup1', 'pattern')
			->will($this->returnValue(['bar' => 'B. Ar']));
		$this->groupManager
			->expects($this->at(3))
			->method('displayNamesInGroup')
			->with('SubGroup2', 'pattern')
			->will($this->returnValue(['foo' => 'M. Foo', 'admin' => 'S. Admin']));
		$this->groupManager
			->expects($this->exactly(3))
			->method('getUserGroupIds')
			->will($this->onConsecutiveCalls(
				['admin', 'SubGroup1', 'testGroup'],
				['SubGroup2', 'SubGroup1'],
				['SubGroup2', 'Foo']
			));
		$this->userManager
			->expects($this->at(0))
			->method('get')
			->with('bar')
			->will($this->returnValue($bar));
		$this->userManager
			->expects($this->at(1))
			->method('get')
			->with('foo')
			->will($this->returnValue($foo));
		$this->userManager
			->expects($this->at(2))
			->method('get')
			->with('admin')
			->will($this->returnValue($admin));

		$subgroup1 = $this->getMockBuilder(IGroup::class)
			->disableOriginalConstructor()
			->getMock();
		$subgroup1->expects($this->any())
			->method('getGID')
			->will($this->returnValue('SubGroup1'));
		$subgroup2 = $this->getMockBuilder(IGroup::class)
			->disableOriginalConstructor()
			->getMock();
		$subgroup2->expects($this->any())
			->method('getGID')
			->will($this->returnValue('SubGroup2'));
		$subadmin = $this->getMockBuilder(SubAdmin::class)
			->disableOriginalConstructor()
			->getMock();
		$subadmin
			->expects($this->at(0))
			->method('getSubAdminsGroups')
			->will($this->returnValue([$subgroup1, $subgroup2]));
		$subadmin
			->expects($this->any())
			->method('getSubAdminsGroups')
			->will($this->returnValue([]));
		$this->groupManager
			->expects($this->any())
			->method('getSubAdmin')
			->will($this->returnValue($subadmin));

		$expectedResponse = new DataResponse(
			[
				0 => [
					'name' => 'bar',
					'displayname' => 'B. Ar',
					'groups' => ['SubGroup1'],
					'subadmin' => [],
					'isEnabled' => false,
					'quota' => 2323,
					'storageLocation' => '/home/bar',
					'lastLogin' => 3999000,
					'backend' => Dummy::class,
					'email' => 'bar@dummy.com',
					'isRestoreDisabled' => false,
					'isAvatarAvailable' => true,
				],
				1=> [
					'name' => 'foo',
					'displayname' => 'M. Foo',
					'groups' => ['SubGroup2', 'SubGroup1'],
					'subadmin' => [],
					'isEnabled' => true,
					'quota' => 1024,
					'storageLocation' => '/home/foo',
					'lastLogin' => 500000,
					'backend' => 'OC_User_Database',
					'email' => 'foo@bar.com',
					'isRestoreDisabled' => false,
					'isAvatarAvailable' => true,
				],
				2 => [
					'name' => 'admin',
					'displayname' => 'S. Admin',
					'groups' => ['SubGroup2'],
					'subadmin' => [],
					'isEnabled' => true,
					'quota' => 404,
					'storageLocation' => '/home/admin',
					'lastLogin' => 12000,
					'backend' => Dummy::class,
					'email' => 'admin@bar.com',
					'isRestoreDisabled' => false,
					'isAvatarAvailable' => false,
				],
			]
		);

		$response = $this->createController()->index(0, 10, '', 'pattern');
		$this->assertEquals($expectedResponse, $response);
	}

	/**
	 * TODO: Since the function uses the static OC_Subadmin class it can't be mocked
	 * to test for subadmins. Thus the test always assumes you have admin permissions...
	 */
	public function testIndexWithSearch() {
		$this->groupManager
			->expects($this->any())
			->method('isAdmin')
			->will($this->returnValue(true));

		$user = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$user->expects($this->any())
			->method('getUID')
			->will($this->returnValue('user'));
		$this->userSession
			->expects($this->any())
			->method('getUser')
			->will($this->returnValue($user));

		$foo = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$foo
			->expects($this->exactly(2))
			->method('getUID')
			->will($this->returnValue('foo'));
		$foo
			->expects($this->once())
			->method('getDisplayName')
			->will($this->returnValue('M. Foo'));
		$foo
			->expects($this->once())
			->method('getEMailAddress')
			->will($this->returnValue('foo@bar.com'));
		$foo
			->expects($this->once())
			->method('isEnabled')
			->will($this->returnValue(true));
		$foo
			->expects($this->once())
			->method('getQuota')
			->will($this->returnValue('1024'));
		$foo
			->method('getLastLogin')
			->will($this->returnValue(500));
		$foo
			->method('getHome')
			->will($this->returnValue('/home/foo'));
		$foo
			->expects($this->once())
			->method('getBackendClassName')
			->will($this->returnValue('OC_User_Database'));
		$admin = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$admin
			->expects($this->exactly(2))
			->method('getUID')
			->will($this->returnValue('admin'));
		$admin
			->expects($this->once())
			->method('getDisplayName')
			->will($this->returnValue('S. Admin'));
		$admin
			->expects($this->once())
			->method('getEMailAddress')
			->will($this->returnValue('admin@bar.com'));
		$admin
			->expects($this->once())
			->method('isEnabled')
			->will($this->returnValue(true));
		$admin
			->expects($this->once())
			->method('getQuota')
			->will($this->returnValue('404'));
		$admin
			->expects($this->once())
			->method('getLastLogin')
			->will($this->returnValue(12));
		$admin
			->expects($this->once())
			->method('getHome')
			->will($this->returnValue('/home/admin'));
		$admin
			->expects($this->once())
			->method('getBackendClassName')
			->will($this->returnValue(Dummy::class));
		$bar = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$bar
			->expects($this->exactly(2))
			->method('getUID')
			->will($this->returnValue('bar'));
		$bar
			->expects($this->once())
			->method('getDisplayName')
			->will($this->returnValue('B. Ar'));
		$bar
			->expects($this->once())
			->method('getEMailAddress')
			->will($this->returnValue('bar@dummy.com'));
		$bar
			->expects($this->once())
			->method('isEnabled')
			->will($this->returnValue(false));
		$bar
			->expects($this->once())
			->method('getQuota')
			->will($this->returnValue('2323'));
		$bar
			->method('getLastLogin')
			->will($this->returnValue(3999));
		$bar
			->method('getHome')
			->will($this->returnValue('/home/bar'));
		$bar
			->expects($this->once())
			->method('getBackendClassName')
			->will($this->returnValue(Dummy::class));

		$this->userManager
			->expects($this->once())
			->method('find')
			->with('pattern', 10, 0)
			->will($this->returnValue([$foo, $admin, $bar]));
		$this->groupManager
			->expects($this->exactly(3))
			->method('getUserGroupIds')
			->will($this->onConsecutiveCalls(['Users', 'Support'], ['admins', 'Support'], ['External Users']));

		$subadmin = $this->getMockBuilder(SubAdmin::class)
			->disableOriginalConstructor()
			->getMock();
		$subadmin->expects($this->any())
			->method('getSubAdminsGroups')
			->will($this->returnValue([]));
		$this->groupManager
			->expects($this->any())
			->method('getSubAdmin')
			->will($this->returnValue($subadmin));

		$expectedResponse = new DataResponse(
			[
				0 => [
					'name' => 'foo',
					'displayname' => 'M. Foo',
					'groups' => ['Users', 'Support'],
					'subadmin' => [],
					'isEnabled' => true,
					'quota' => 1024,
					'storageLocation' => '/home/foo',
					'lastLogin' => 500000,
					'backend' => 'OC_User_Database',
					'email' => 'foo@bar.com',
					'isRestoreDisabled' => false,
					'isAvatarAvailable' => true,
				],
				1 => [
					'name' => 'admin',
					'displayname' => 'S. Admin',
					'groups' => ['admins', 'Support'],
					'subadmin' => [],
					'isEnabled' => true,
					'quota' => 404,
					'storageLocation' => '/home/admin',
					'lastLogin' => 12000,
					'backend' => Dummy::class,
					'email' => 'admin@bar.com',
					'isRestoreDisabled' => false,
					'isAvatarAvailable' => false,
				],
				2 => [
					'name' => 'bar',
					'displayname' => 'B. Ar',
					'groups' => ['External Users'],
					'subadmin' => [],
					'isEnabled' => false,
					'quota' => 2323,
					'storageLocation' => '/home/bar',
					'lastLogin' => 3999000,
					'backend' => Dummy::class,
					'email' => 'bar@dummy.com',
					'isRestoreDisabled' => false,
					'isAvatarAvailable' => true,
				],
			]
		);
		$response = $this->createController()->index(0, 10, '', 'pattern');
		$this->assertEquals($expectedResponse, $response);
	}

	public function testIndexWithBackend() {
		$this->groupManager
			->expects($this->any())
			->method('isAdmin')
			->will($this->returnValue(true));

		$user = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$user->expects($this->any())
			->method('getUID')
			->will($this->returnValue('user'));
		$this->userSession
			->expects($this->any())
			->method('getUser')
			->will($this->returnValue($user));

		$user = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$user
			->expects($this->exactly(2))
			->method('getUID')
			->will($this->returnValue('foo'));
		$user
			->expects($this->once())
			->method('getDisplayName')
			->will($this->returnValue('M. Foo'));
		$user
			->expects($this->once())
			->method('getEMailAddress')
			->will($this->returnValue(null));
		$user
			->expects($this->once())
			->method('isEnabled')
			->will($this->returnValue(true));
		$user
			->expects($this->once())
			->method('getQuota')
			->will($this->returnValue('none'));
		$user
			->method('getLastLogin')
			->will($this->returnValue(500));
		$user
			->method('getHome')
			->will($this->returnValue('/home/foo'));
		$user
			->expects($this->once())
			->method('getBackendClassName')
			->will($this->returnValue('OC_User_Database'));
		$this->userManager
			->expects($this->once())
			->method('getBackends')
			->will($this->returnValue([new \OC\User\Database()]));
		$this->userManager
			->expects($this->once())
			->method('clearBackends');
		$this->userManager
			->expects($this->once())
			->method('find')
			->with('')
			->will($this->returnValue([$user]));

		$subadmin = $this->getMockBuilder(SubAdmin::class)
			->disableOriginalConstructor()
			->getMock();
		$subadmin->expects($this->once())
			->method('getSubAdminsGroups')
			->will($this->returnValue([]));
		$this->groupManager
			->expects($this->any())
			->method('getSubAdmin')
			->will($this->returnValue($subadmin));

		$expectedResponse = new DataResponse(
			[
				0 => [
					'name' => 'foo',
					'displayname' => 'M. Foo',
					'groups' => null,
					'subadmin' => [],
					'isEnabled' => true,
					'quota' => 'none',
					'storageLocation' => '/home/foo',
					'lastLogin' => 500000,
					'backend' => 'OC_User_Database',
					'email' => null,
					'isRestoreDisabled' => false,
					'isAvatarAvailable' => true,
				]
			]
		);
		$response = $this->createController()->index(0, 10, '','', Dummy::class);
		$this->assertEquals($expectedResponse, $response);
	}

	public function testIndexWithBackendNoUser() {
		$this->groupManager
			->expects($this->any())
			->method('isAdmin')
			->will($this->returnValue(true));

		$user = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$user->expects($this->any())
			->method('getUID')
			->will($this->returnValue('user'));
		$this->userSession
			->expects($this->any())
			->method('getUser')
			->will($this->returnValue($user));

		$this->userManager
			->expects($this->once())
			->method('getBackends')
			->will($this->returnValue([new \OC\User\Database()]));
		$this->userManager
			->expects($this->once())
			->method('find')
			->with('')
			->will($this->returnValue([]));

		$expectedResponse = new DataResponse([]);
		$response = $this->createController()->index(0, 10, '','', Dummy::class);
		$this->assertEquals($expectedResponse, $response);
	}

	public function testCreateSuccessfulWithoutGroupAdmin() {
		$this->groupManager
			->expects($this->any())
			->method('isAdmin')
			->will($this->returnValue(true));

		$user = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$user->expects($this->any())
			->method('getUID')
			->will($this->returnValue('user'));
		$this->userSession
			->expects($this->any())
			->method('getUser')
			->will($this->returnValue($user));

		$user = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$user
			->method('getHome')
			->will($this->returnValue('/home/user'));
		$user
			->method('getUID')
			->will($this->returnValue('foo'));
		$user
			->expects($this->once())
			->method('getBackendClassName')
			->will($this->returnValue('bar'));

		$this->userManager
			->expects($this->once())
			->method('createUser')
			->will($this->onConsecutiveCalls($user));

		$subadmin = $this->getMockBuilder(SubAdmin::class)
			->disableOriginalConstructor()
			->getMock();
		$subadmin
			->expects($this->any())
			->method('getSubAdminsGroups')
			->with($user)
			->will($this->returnValue([]));
		$this->groupManager
			->expects($this->any())
			->method('getSubAdmin')
			->will($this->returnValue($subadmin));

		$expectedResponse = new DataResponse(
			[
				'name' => 'foo',
				'groups' => null,
				'storageLocation' => '/home/user',
				'backend' => 'bar',
				'lastLogin' => null,
				'displayname' => null,
				'isEnabled' => null,
				'quota' => null,
				'subadmin' => [],
				'email' => null,
				'isRestoreDisabled' => false,
				'isAvatarAvailable' => true,
			],
			Http::STATUS_CREATED
		);
		$response = $this->createController()->create('foo', 'password', []);
		$this->assertEquals($expectedResponse, $response);
	}

	public function testCreateSuccessfulWithoutGroupSubAdmin() {
		$this->groupManager
			->expects($this->any())
			->method('isAdmin')
			->will($this->returnValue(false));

		$user = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$user->expects($this->any())
			->method('getUID')
			->will($this->returnValue('user'));
		$this->userSession
			->expects($this->any())
			->method('getUser')
			->will($this->returnValue($user));

		$newUser = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$newUser
			->method('getUID')
			->will($this->returnValue('foo'));
		$newUser
			->method('getHome')
			->will($this->returnValue('/home/user'));
		$newUser
			->method('getHome')
			->will($this->returnValue('/home/user'));
		$newUser
			->expects($this->once())
			->method('getBackendClassName')
			->will($this->returnValue('bar'));
		$user = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$subGroup1 = $this->getMockBuilder(IGroup::class)
			->disableOriginalConstructor()->getMock();
		$subGroup1
			->expects($this->once())
			->method('addUser')
			->with($newUser);
		$subGroup2 = $this->getMockBuilder(IGroup::class)
			->disableOriginalConstructor()->getMock();
		$subGroup2
			->expects($this->once())
			->method('addUser')
			->with($newUser);

		$this->userManager
			->expects($this->once())
			->method('createUser')
			->will($this->returnValue($newUser));
		$this->groupManager
			->expects($this->exactly(2))
			->method('get')
			->will($this->onConsecutiveCalls($subGroup1, $subGroup2));
		$this->groupManager
			->expects($this->once())
			->method('getUserGroupIds')
			->with($user)
			->will($this->onConsecutiveCalls(['SubGroup1', 'SubGroup2']));

		$subadmin = $this->getMockBuilder(SubAdmin::class)
			->disableOriginalConstructor()
			->getMock();
		$subadmin
			->expects($this->at(0))
			->method('getSubAdminsGroups')
			->will($this->returnValue([$subGroup1, $subGroup2]));
		$subadmin
			->expects($this->at(1))
			->method('getSubAdminsGroups')
			->will($this->returnValue([]));
		$this->groupManager
			->expects($this->any())
			->method('getSubAdmin')
			->will($this->returnValue($subadmin));

		$expectedResponse = new DataResponse(
			[
				'name' => 'foo',
				'groups' => ['SubGroup1', 'SubGroup2'],
				'storageLocation' => '/home/user',
				'backend' => 'bar',
				'lastLogin' => 0,
				'displayname' => null,
				'isEnabled' => null,
				'quota' => null,
				'subadmin' => [],
				'email' => null,
				'isRestoreDisabled' => false,
				'isAvatarAvailable' => true,
			],
			Http::STATUS_CREATED
		);
		$response = $this->createController()->create('foo', 'password');
		$this->assertEquals($expectedResponse, $response);
	}

	public function testCreateSuccessfulWithGroupAdmin() {
		$this->groupManager
			->expects($this->any())
			->method('isAdmin')
			->will($this->returnValue(true));

		$user = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$user->expects($this->any())
			->method('getUID')
			->will($this->returnValue('user'));
		$this->userSession
			->expects($this->any())
			->method('getUser')
			->will($this->returnValue($user));

		$user = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$user
			->method('getHome')
			->will($this->returnValue('/home/user'));
		$user
			->method('getHome')
			->will($this->returnValue('/home/user'));
		$user
			->method('getUID')
			->will($this->returnValue('foo'));
		$user
			->expects($this->once())
			->method('getBackendClassName')
			->will($this->returnValue('bar'));
		$existingGroup = $this->getMockBuilder(IGroup::class)
			->disableOriginalConstructor()->getMock();
		$existingGroup
			->expects($this->once())
			->method('addUser')
			->with($user);
		$newGroup = $this->getMockBuilder(IGroup::class)
			->disableOriginalConstructor()->getMock();
		$newGroup
			->expects($this->once())
			->method('addUser')
			->with($user);

		$this->userManager
			->expects($this->once())
			->method('createUser')
			->will($this->onConsecutiveCalls($user));
		$this->groupManager
			->expects($this->exactly(2))
			->method('get')
			->will($this->onConsecutiveCalls(null, $existingGroup));
		$this->groupManager
			->expects($this->once())
			->method('createGroup')
			->with('NewGroup')
			->will($this->onConsecutiveCalls($newGroup));
		$this->groupManager
			->expects($this->once())
			->method('getUserGroupIds')
			->with($user)
			->will($this->onConsecutiveCalls(['NewGroup', 'ExistingGroup']));

		$subadmin = $this->getMockBuilder(SubAdmin::class)
			->disableOriginalConstructor()
			->getMock();
		$subadmin
			->expects($this->once())
			->method('getSubAdminsGroups')
			->with($user)
			->will($this->returnValue([]));
		$this->groupManager
			->expects($this->any())
			->method('getSubAdmin')
			->will($this->returnValue($subadmin));

		$expectedResponse = new DataResponse(
			[
				'name' => 'foo',
				'groups' => ['NewGroup', 'ExistingGroup'],
				'storageLocation' => '/home/user',
				'backend' => 'bar',
				'lastLogin' => null,
				'displayname' => null,
				'isEnabled' => null,
				'quota' => null,
				'subadmin' => [],
				'email' => null,
				'isRestoreDisabled' => false,
				'isAvatarAvailable' => true,
			],
			Http::STATUS_CREATED
		);
		$response = $this->createController()->create('foo', 'password', ['NewGroup', 'ExistingGroup']);
		$this->assertEquals($expectedResponse, $response);
	}

	public function testCreateSuccessfulWithGroupSubAdmin() {
		$this->groupManager
			->expects($this->any())
			->method('isAdmin')
			->will($this->returnValue(false));

		$user = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$user->expects($this->any())
			->method('getUID')
			->will($this->returnValue('user'));
		$this->userSession
			->expects($this->any())
			->method('getUser')
			->will($this->returnValue($user));
		$newUser = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$newUser
			->method('getHome')
			->will($this->returnValue('/home/user'));
		$newUser
			->method('getHome')
			->will($this->returnValue('/home/user'));
		$newUser
			->method('getUID')
			->will($this->returnValue('foo'));
		$newUser
			->expects($this->once())
			->method('getBackendClassName')
			->will($this->returnValue('bar'));
		$subGroup1 = $this->getMockBuilder(IGroup::class)
			->disableOriginalConstructor()->getMock();
		$subGroup1
			->expects($this->any())
			->method('getGID')
			->will($this->returnValue('SubGroup1'));
		$subGroup1
			->expects($this->once())
			->method('addUser')
			->with($user);
		$this->userManager
			->expects($this->once())
			->method('createUser')
			->will($this->returnValue($newUser));
		$this->groupManager
			->expects($this->at(1))
			->method('get')
			->with('SubGroup1')
			->will($this->returnValue($subGroup1));
		$this->groupManager
			->expects($this->at(5))
			->method('get')
			->with('SubGroup1')
			->will($this->returnValue($subGroup1));
		$this->groupManager
			->expects($this->once())
			->method('getUserGroupIds')
			->with($user)
			->will($this->onConsecutiveCalls(['SubGroup1']));
		$this->groupManager
			->expects($this->once())
			->method('getUserGroupIds')
			->with($newUser)
			->will($this->onConsecutiveCalls(['SubGroup1']));

		$subadmin = $this->getMockBuilder(SubAdmin::class)
			->disableOriginalConstructor()
			->getMock();
		$subadmin->expects($this->at(1))
			->method('getSubAdminsGroups')
			->with($user)
			->will($this->returnValue([$subGroup1]));
		$subadmin->expects($this->at(2))
			->method('getSubAdminsGroups')
			->with($newUser)
			->will($this->returnValue([]));
		$this->groupManager
			->expects($this->any())
			->method('getSubAdmin')
			->will($this->returnValue($subadmin));

		$expectedResponse = new DataResponse(
			[
				'name' => 'foo',
				'groups' => ['SubGroup1'],
				'storageLocation' => '/home/user',
				'backend' => 'bar',
				'lastLogin' => 0,
				'displayname' => null,
				'isEnabled' => null,
				'quota' => null,
				'subadmin' => [],
				'email' => null,
				'isRestoreDisabled' => false,
				'isAvatarAvailable' => true,
			],
			Http::STATUS_CREATED
		);
		$response = $this->createController()->create('foo', 'password', ['SubGroup1', 'ExistingGroup']);
		$this->assertEquals($expectedResponse, $response);
	}

	public function testCreateUnsuccessfulAdmin() {
		$this->groupManager
			->expects($this->any())
			->method('isAdmin')
			->will($this->returnValue(true));

		$user = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$user->expects($this->any())
			->method('getUID')
			->will($this->returnValue('user'));
		$this->userSession
			->expects($this->any())
			->method('getUser')
			->will($this->returnValue($user));

		$this->userManager
			->method('createUser')
			->will($this->throwException(new \Exception()));

		$expectedResponse = new DataResponse(
			[
				'message' => 'Unable to create user.'
			],
			Http::STATUS_FORBIDDEN
		);
		$response = $this->createController()->create('foo', 'password', []);
		$this->assertEquals($expectedResponse, $response);
	}

	public function testCreateUnsuccessfulSubAdmin() {
		$this->groupManager
			->expects($this->any())
			->method('isAdmin')
			->will($this->returnValue(false));

		$user = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$user
			->expects($this->any())
			->method('getUID')
			->will($this->returnValue('username'));
		$this->userSession
			->expects($this->any())
			->method('getUser')
			->will($this->returnValue($user));

		$this->userManager
			->method('createUser')
			->will($this->throwException(new \Exception()));

		$subgroup1 = $this->getMockBuilder(IGroup::class)
			->disableOriginalConstructor()
			->getMock();
		$subgroup1->expects($this->once())
			->method('getGID')
			->will($this->returnValue('SubGroup1'));
		$subgroup2 = $this->getMockBuilder(IGroup::class)
			->disableOriginalConstructor()
			->getMock();
		$subgroup2->expects($this->once())
			->method('getGID')
			->will($this->returnValue('SubGroup2'));
		$subadmin = $this->getMockBuilder(SubAdmin::class)
			->disableOriginalConstructor()
			->getMock();
		$subadmin->expects($this->once())
			->method('getSubAdminsGroups')
			->with($user)
			->will($this->returnValue([$subgroup1, $subgroup2]));
		$this->groupManager
			->expects($this->any())
			->method('getSubAdmin')
			->will($this->returnValue($subadmin));

		$expectedResponse = new DataResponse(
			[
				'message' => 'Unable to create user.'
			],
			Http::STATUS_FORBIDDEN
		);
		$response = $this->createController()->create('foo', 'password', []);
		$this->assertEquals($expectedResponse, $response);
	}

	public function testDestroySelfAdmin() {
		$this->groupManager
			->expects($this->any())
			->method('isAdmin')
			->will($this->returnValue(true));

		$user = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$user
			->expects($this->any())
			->method('getUID')
			->will($this->returnValue('myself'));
		$this->userSession
			->method('getUser')
			->will($this->returnValue($user));

		$expectedResponse = new DataResponse(
			[
				'status' => 'error',
				'data' => [
					'message' => 'Unable to delete user.'
				]
			],
			Http::STATUS_FORBIDDEN
		);
		$response = $this->createController()->destroy('myself');
		$this->assertEquals($expectedResponse, $response);
	}

	public function testDestroySelfSubadmin() {
		$this->groupManager
			->expects($this->any())
			->method('isAdmin')
			->will($this->returnValue(false));


		$user = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$user
			->expects($this->any())
			->method('getUID')
			->will($this->returnValue('myself'));
		$this->userSession
			->method('getUser')
			->will($this->returnValue($user));

		$expectedResponse = new DataResponse(
			[
				'status' => 'error',
				'data' => [
					'message' => 'Unable to delete user.'
				]
			],
			Http::STATUS_FORBIDDEN
		);
		$response = $this->createController()->destroy('myself');
		$this->assertEquals($expectedResponse, $response);
	}

	public function testDestroyAdmin() {
		$this->groupManager
			->expects($this->any())
			->method('isAdmin')
			->will($this->returnValue(true));

		$user = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$user
			->expects($this->any())
			->method('getUID')
			->will($this->returnValue('Admin'));
		$toDeleteUser = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$toDeleteUser
			->expects($this->once())
			->method('delete')
			->will($this->returnValue(true));
		$this->userSession
			->method('getUser')
			->will($this->returnValue($user));
		$this->userManager
			->method('get')
			->with('UserToDelete')
			->will($this->returnValue($toDeleteUser));

		$expectedResponse = new DataResponse(
			[
				'status' => 'success',
				'data' => [
					'username' => 'UserToDelete'
				]
			],
			Http::STATUS_NO_CONTENT
		);
		$response = $this->createController()->destroy('UserToDelete');
		$this->assertEquals($expectedResponse, $response);
	}

	public function testDestroySubAdmin() {
		$this->groupManager
			->expects($this->any())
			->method('isAdmin')
			->will($this->returnValue(false));

		$user = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$user
			->expects($this->any())
			->method('getUID')
			->will($this->returnValue('myself'));
		$this->userSession
			->method('getUser')
			->will($this->returnValue($user));

		$user = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$toDeleteUser = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$toDeleteUser
			->expects($this->once())
			->method('delete')
			->will($this->returnValue(true));
		$this->userSession
			->method('getUser')
			->will($this->returnValue($user));
		$this->userManager
			->method('get')
			->with('UserToDelete')
			->will($this->returnValue($toDeleteUser));

		$subadmin = $this->getMockBuilder(SubAdmin::class)
			->disableOriginalConstructor()
			->getMock();
		$subadmin->expects($this->once())
			->method('isUserAccessible')
			->with($user, $toDeleteUser)
			->will($this->returnValue(true));
		$this->groupManager
			->expects($this->any())
			->method('getSubAdmin')
			->will($this->returnValue($subadmin));

		$expectedResponse = new DataResponse(
			[
				'status' => 'success',
				'data' => [
					'username' => 'UserToDelete'
				]
			],
			Http::STATUS_NO_CONTENT
		);
		$response = $this->createController()->destroy('UserToDelete');
		$this->assertEquals($expectedResponse, $response);
	}

	public function testDestroyUnsuccessfulAdmin() {
		$this->groupManager
			->expects($this->any())
			->method('isAdmin')
			->will($this->returnValue(true));

		$user = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$user
			->expects($this->any())
			->method('getUID')
			->will($this->returnValue('Admin'));
		$toDeleteUser = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$toDeleteUser
			->expects($this->once())
			->method('delete')
			->will($this->returnValue(false));
		$this->userSession
			->method('getUser')
			->will($this->returnValue($user));
		$this->userManager
			->method('get')
			->with('UserToDelete')
			->will($this->returnValue($toDeleteUser));

		$expectedResponse = new DataResponse(
			[
				'status' => 'error',
				'data' => [
					'message' => 'Unable to delete user.'
				]
			],
			Http::STATUS_FORBIDDEN
		);
		$response = $this->createController()->destroy('UserToDelete');
		$this->assertEquals($expectedResponse, $response);
	}

	public function testDestroyUnsuccessfulSubAdmin() {
		$this->groupManager
			->expects($this->any())
			->method('isAdmin')
			->will($this->returnValue(false));

		$user = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$user
			->expects($this->any())
			->method('getUID')
			->will($this->returnValue('myself'));
		$this->userSession
			->method('getUser')
			->will($this->returnValue($user));

		$toDeleteUser = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$toDeleteUser
			->expects($this->once())
			->method('delete')
			->will($this->returnValue(false));
		$this->userSession
			->method('getUser')
			->will($this->returnValue($user));
		$this->userManager
			->method('get')
			->with('UserToDelete')
			->will($this->returnValue($toDeleteUser));

		$subadmin = $this->getMockBuilder(SubAdmin::class)
			->disableOriginalConstructor()
			->getMock();
		$subadmin->expects($this->once())
			->method('isUserAccessible')
			->with($user, $toDeleteUser)
			->will($this->returnValue(true));
		$this->groupManager
			->expects($this->any())
			->method('getSubAdmin')
			->will($this->returnValue($subadmin));

		$expectedResponse = new DataResponse(
			[
				'status' => 'error',
				'data' => [
					'message' => 'Unable to delete user.'
				]
			],
			Http::STATUS_FORBIDDEN
		);
		$response = $this->createController()->destroy('UserToDelete');
		$this->assertEquals($expectedResponse, $response);
	}

	public function testDestroyNotAccessibleToSubAdmin() {
		$this->groupManager
			->expects($this->any())
			->method('isAdmin')
			->will($this->returnValue(false));

		$user = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$user
			->expects($this->any())
			->method('getUID')
			->will($this->returnValue('myself'));
		$this->userSession
			->method('getUser')
			->will($this->returnValue($user));

		$toDeleteUser = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$this->userSession
			->method('getUser')
			->will($this->returnValue($user));
		$this->userManager
			->method('get')
			->with('UserToDelete')
			->will($this->returnValue($toDeleteUser));

		$subadmin = $this->getMockBuilder(SubAdmin::class)
			->disableOriginalConstructor()
			->getMock();
		$subadmin->expects($this->once())
			->method('isUserAccessible')
			->with($user, $toDeleteUser)
			->will($this->returnValue(false));
		$this->groupManager
			->expects($this->any())
			->method('getSubAdmin')
			->will($this->returnValue($subadmin));

		$expectedResponse = new DataResponse(
			[
				'status' => 'error',
				'data' => [
					'message' => 'Authentication error'
				]
			],
			Http::STATUS_FORBIDDEN
		);
		$response = $this->createController()->destroy('UserToDelete');
		$this->assertEquals($expectedResponse, $response);
	}

	/**
	 * test if an invalid mail result in a failure response
	 */
	public function testCreateUnsuccessfulWithInvalidEmailAdmin() {
		$this->groupManager
			->expects($this->any())
			->method('isAdmin')
			->will($this->returnValue(true));

		$user = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$user->expects($this->any())
			->method('getUID')
			->will($this->returnValue('user'));
		$this->userSession
			->expects($this->any())
			->method('getUser')
			->will($this->returnValue($user));

		$expectedResponse = new DataResponse([
				'message' => 'Invalid mail address',
			],
			Http::STATUS_UNPROCESSABLE_ENTITY
		);
		$response = $this->createController()->create('foo', 'password', [], 'invalidMailAdress');
		$this->assertEquals($expectedResponse, $response);
	}

	/**
	 * test if a valid mail result in a successful mail send
	 */
	public function testCreateSuccessfulWithValidEmailAdmin() {
		$this->groupManager
			->expects($this->any())
			->method('isAdmin')
			->will($this->returnValue(true));

		$user = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$user->expects($this->any())
			->method('getUID')
			->will($this->returnValue('user'));
		$this->userSession
			->expects($this->any())
			->method('getUser')
			->will($this->returnValue($user));

		$message = $this->getMockBuilder(Message::class)
			->disableOriginalConstructor()->getMock();
		$message
			->expects($this->at(0))
			->method('setTo')
			->with(['validMail@Adre.ss' => 'foo']);
		$message
			->expects($this->at(1))
			->method('setSubject')
			->with('Your  account was created');
		$htmlBody = new Http\TemplateResponse(
			'user_management',
			'new_user/email-html',
			[
				'username' => 'foo',
				'url' => '',
			],
			'blank'
		);
		$message
			->expects($this->at(2))
			->method('setHtmlBody')
			->with($htmlBody->render());
		$plainBody = new Http\TemplateResponse(
			'user_management',
			'new_user/email-plain_text',
			[
				'username' => 'foo',
				'url' => '',
			],
			'blank'
		);
		$message
			->expects($this->at(3))
			->method('setPlainBody')
			->with($plainBody->render());
		$message
			->expects($this->at(4))
			->method('setFrom')
			->with(['no-reply@localhost' => null]);

		$this->mailer
			->expects($this->at(0))
			->method('validateMailAddress')
			->with('validMail@Adre.ss')
			->will($this->returnValue(true));
		$this->mailer
			->expects($this->at(1))
			->method('createMessage')
			->will($this->returnValue($message));
		$this->mailer
			->expects($this->at(2))
			->method('send')
			->with($message);

		$user = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$user
			->method('getHome')
			->will($this->returnValue('/home/user'));
		$user
			->method('getHome')
			->will($this->returnValue('/home/user'));
		$user
			->method('getUID')
			->will($this->returnValue('foo'));
		$user
			->expects($this->once())
			->method('getBackendClassName')
			->will($this->returnValue('bar'));

		$this->userManager
			->expects($this->once())
			->method('createUser')
			->will($this->onConsecutiveCalls($user));
		$subadmin = $this->getMockBuilder(SubAdmin::class)
			->disableOriginalConstructor()
			->getMock();
		$subadmin->expects($this->once())
			->method('getSubAdminsGroups')
			->with($user)
			->will($this->returnValue([]));
		$this->groupManager
			->expects($this->any())
			->method('getSubAdmin')
			->will($this->returnValue($subadmin));

		$response = $this->createController()->create('foo', 'password', [], 'validMail@Adre.ss');
		$this->assertEquals(Http::STATUS_CREATED, $response->getStatus());
	}

	private function mockUser($userId = 'foo', $displayName = 'M. Foo', $isEnabled = true,
							  $lastLogin = 500, $home = '/home/foo', $backend = 'OC_User_Database') {
		$user = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$user
			->expects($this->any())
			->method('getUID')
			->will($this->returnValue($userId));
		$user
			->expects($this->once())
			->method('getDisplayName')
			->will($this->returnValue($displayName));
		$user
			->method('isEnabled')
			->will($this->returnValue($isEnabled));
		$user
			->method('getLastLogin')
			->will($this->returnValue($lastLogin));
		$user
			->method('getHome')
			->will($this->returnValue($home));
		$user
			->expects($this->once())
			->method('getBackendClassName')
			->will($this->returnValue($backend));

		$result = [
			'name' => $userId,
			'displayname' => $displayName,
			'groups' => null,
			'subadmin' => [],
			'isEnabled' => $isEnabled,
			'quota' => null,
			'storageLocation' => $home,
			'lastLogin' => $lastLogin * 1000,
			'backend' => $backend,
			'email' => null,
			'isRestoreDisabled' => false,
			'isAvatarAvailable' => true,
		];

		return [$user, $result];
	}

	public function testRestorePossibleWithoutEncryption() {
		$this->groupManager
			->expects($this->any())
			->method('isAdmin')
			->will($this->returnValue(true));

		$user = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$user->expects($this->any())
			->method('getUID')
			->will($this->returnValue('user'));
		$this->userSession
			->expects($this->any())
			->method('getUser')
			->will($this->returnValue($user));

		list($user, $expectedResult) = $this->mockUser();

		$subadmin = $this->getMockBuilder(SubAdmin::class)
			->disableOriginalConstructor()
			->getMock();
		$subadmin->expects($this->once())
			->method('getSubAdminsGroups')
			->with($user)
			->will($this->returnValue([]));
		$this->groupManager
			->expects($this->any())
			->method('getSubAdmin')
			->will($this->returnValue($subadmin));

		$result = self::invokePrivate($this->createController(), 'formatUserForIndex', [$user]);
		$this->assertEquals($expectedResult, $result);
	}

	public function testRestorePossibleWithAdminAndUserRestore() {
		$this->groupManager
			->expects($this->any())
			->method('isAdmin')
			->will($this->returnValue(true));

		$user = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$user->expects($this->any())
			->method('getUID')
			->will($this->returnValue('user'));
		$this->userSession
			->expects($this->any())
			->method('getUser')
			->will($this->returnValue($user));

		list($user, $expectedResult) = $this->mockUser();

		$this->appManager
			->expects($this->once())
			->method('isEnabledForUser')
			->with(
				$this->equalTo('encryption')
			)
			->will($this->returnValue(true));
		$this->config
			->expects($this->once())
			->method('getAppValue')
			->with(
				$this->equalTo('encryption'),
				$this->equalTo('recoveryAdminEnabled'),
				$this->anything()
			)
			->will($this->returnValue('1'));

		$this->config
			->expects($this->at(1))
			->method('getUserValue')
			->with(
				$this->anything(),
				$this->equalTo('encryption'),
				$this->equalTo('recoveryEnabled'),
				$this->anything()
			)
			->will($this->returnValue('1'));

		$subadmin = $this->getMockBuilder(SubAdmin::class)
			->disableOriginalConstructor()
			->getMock();
		$subadmin->expects($this->once())
			->method('getSubAdminsGroups')
			->with($user)
			->will($this->returnValue([]));
		$this->groupManager
			->expects($this->any())
			->method('getSubAdmin')
			->will($this->returnValue($subadmin));

		$result = self::invokePrivate($this->createController(), 'formatUserForIndex', [$user]);
		$this->assertEquals($expectedResult, $result);
	}

	public function testRestoreNotPossibleWithoutAdminRestore() {
		$this->groupManager
			->expects($this->any())
			->method('isAdmin')
			->will($this->returnValue(true));

		$user = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$user->expects($this->any())
			->method('getUID')
			->will($this->returnValue('user'));
		$this->userSession
			->expects($this->any())
			->method('getUser')
			->will($this->returnValue($user));

		list($user, $expectedResult) = $this->mockUser();

		$this->appManager
			->method('isEnabledForUser')
			->with(
				$this->equalTo('encryption')
			)
			->will($this->returnValue(true));

		$expectedResult['isRestoreDisabled'] = true;

		$subadmin = $this->getMockBuilder(SubAdmin::class)
			->disableOriginalConstructor()
			->getMock();
		$subadmin->expects($this->once())
			->method('getSubAdminsGroups')
			->with($user)
			->will($this->returnValue([]));
		$this->groupManager
			->expects($this->any())
			->method('getSubAdmin')
			->will($this->returnValue($subadmin));

		$result = self::invokePrivate($this->createController(), 'formatUserForIndex', [$user]);
		$this->assertEquals($expectedResult, $result);
	}

	public function testRestoreNotPossibleWithoutUserRestore() {
		$this->groupManager
			->expects($this->any())
			->method('isAdmin')
			->will($this->returnValue(true));

		$user = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$user->expects($this->any())
			->method('getUID')
			->will($this->returnValue('user'));
		$this->userSession
			->expects($this->any())
			->method('getUser')
			->will($this->returnValue($user));

		list($user, $expectedResult) = $this->mockUser();

		$this->appManager
			->expects($this->once())
			->method('isEnabledForUser')
			->with(
				$this->equalTo('encryption')
			)
			->will($this->returnValue(true));
		$this->config
			->expects($this->once())
			->method('getAppValue')
			->with(
				$this->equalTo('encryption'),
				$this->equalTo('recoveryAdminEnabled'),
				$this->anything()
			)
			->will($this->returnValue('1'));

		$this->config
			->expects($this->at(1))
			->method('getUserValue')
			->with(
				$this->anything(),
				$this->equalTo('encryption'),
				$this->equalTo('recoveryEnabled'),
				$this->anything()
			)
			->will($this->returnValue('0'));

		$expectedResult['isRestoreDisabled'] = true;

		$subadmin = $this->getMockBuilder(SubAdmin::class)
			->disableOriginalConstructor()
			->getMock();
		$subadmin->expects($this->once())
			->method('getSubAdminsGroups')
			->with($user)
			->will($this->returnValue([]));
		$this->groupManager
			->expects($this->any())
			->method('getSubAdmin')
			->will($this->returnValue($subadmin));

		$result = self::invokePrivate($this->createController(), 'formatUserForIndex', [$user]);
		$this->assertEquals($expectedResult, $result);
	}

	public function testNoAvatar() {
		$this->groupManager
			->expects($this->any())
			->method('isAdmin')
			->will($this->returnValue(true));

		$user = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$user->expects($this->any())
			->method('getUID')
			->will($this->returnValue('user'));
		$this->userSession
			->expects($this->any())
			->method('getUser')
			->will($this->returnValue($user));

		list($user, $expectedResult) = $this->mockUser();

		$subadmin = $this->getMockBuilder(SubAdmin::class)
			->disableOriginalConstructor()
			->getMock();
		$subadmin->expects($this->once())
			->method('getSubAdminsGroups')
			->with($user)
			->will($this->returnValue([]));
		$this->groupManager
			->expects($this->any())
			->method('getSubAdmin')
			->will($this->returnValue($subadmin));

		$this->avatarManager
			->method('getAvatar')
			->will($this->throwException(new \OCP\Files\NotFoundException()));
		$expectedResult['isAvatarAvailable'] = false;

		$result = self::invokePrivate($this->createController(), 'formatUserForIndex', [$user]);
		$this->assertEquals($expectedResult, $result);
	}

	public function dataforemailaddress() {
		return [
			['foo', 'foo' , 'foo@localhost'],
			['bar', 'foo', 'foo@localhoster']
		];
	}

	/**
	 * Test to verify setting email address by user who had logged in
	 * for itself.
	 *
	 * @dataProvider dataforemailaddress
	 * @param $loginUser
	 * @param $setUser
	 * @param $emailAddress
	 */
	public function testSetSelfEmailAddress($loginUser, $setUser, $emailAddress) {

		$appName = 'user_management';
		$irequest = $this->createMock(IRequest::class);
		$userManager = $this->createMock(IUserManager::class);
		$groupManager = $this->createMock(IGroupManager::class);
		$userSession = $this->createMock(IUserSession::class);
		$iConfig = $this->createMock(IConfig::class);
		$iSecureRandom = $this->createMock(ISecureRandom::class);
		$iL10 = $this->createMock(IL10N::class);
		$iLogger = $this->createMock(ILogger::class);
		$ocDefault = $this->getMockBuilder(\OC_Defaults::class)
			->disableOriginalConstructor()->getMock();
		$iMailer = $this->createMock(IMailer::class);
		$iTimeFactory = $this->createMock(ITimeFactory::class);
		$urlGenerator = $this->createMock(IURLGenerator::class);
		$appManager = $this->createMock(IAppManager::class);
		$iAvatarManager = $this->createMock(IAvatarManager::class);
		$userController = new UsersController($appName, $irequest, $userManager, $groupManager,
			$userSession, $iConfig, $iSecureRandom, $iL10, $iLogger, $ocDefault, $iMailer,
			$iTimeFactory, $urlGenerator, $appManager, $iAvatarManager);

		$iUser = $this->createMock(IUser::class);
		$userManager->method('get')->willReturn($iUser);
		$iUser->method('getUID')->willReturn($loginUser);
		$userSession->expects($this->any())
			->method('getUser')
			->willReturn($iUser);
		$subAdmin = $this->createMock(SubAdmin::class);
		$subAdmin->method('isSubAdmin')->with($iUser)->willReturn(false);
		$groupManager->expects($this->any())
			->method('getSubAdmin')
			->willReturn($subAdmin);
		$response = $userController->setEmailAddress($setUser, $emailAddress);
		if ($loginUser !== $setUser) {
			$this->assertEquals( new Http\JSONResponse([
				'error' => 'cannotSetEmailAddress',
				'message' => 'Cannot set email address for user'
			], HTTP::STATUS_NOT_FOUND), $response);
		} else {
			$this->assertEquals(new Http\JSONResponse(), $response);
		}
	}

	public function setDataForSendMail() {
		return [
			['foo', 'foo@localhost'],
			['bar', 'bar@localhost']
		];
	}

	/**
	 * A test to verify if the email is send and verify data response for
	 * the success
	 *
	 * @dataProvider setDataForSendMail
	 * @param $id
	 * @param $mailaddress
	 */
	public function testSetEmailAddressSendEmail($id, $mailaddress) {

		$appName = 'settings';
		$irequest = $this->createMock(IRequest::class);
		$userManager = $this->createMock(IUserManager::class);
		$groupManager = $this->createMock(IGroupManager::class);
		$userSession = $this->createMock(IUserSession::class);
		$iConfig = $this->createMock(IConfig::class);
		$iSecureRandom = $this->createMock(ISecureRandom::class);
		$iL10 = $this->createMock(IL10N::class);
		$iLogger = $this->createMock(ILogger::class);
		$ocDefault = $this->getMockBuilder(\OC_Defaults::class)
			->disableOriginalConstructor()->getMock();
		$iMailer = $this->createMock(IMailer::class);
		$iTimeFactory = $this->createMock(ITimeFactory::class);
		$urlGenerator = $this->createMock(IURLGenerator::class);
		$appManager = $this->createMock(IAppManager::class);
		$iAvatarManager = $this->createMock(IAvatarManager::class);
		$userController = new UsersController($appName, $irequest, $userManager, $groupManager,
			$userSession, $iConfig, $iSecureRandom, $iL10, $iLogger, $ocDefault, $iMailer,
			$iTimeFactory, $urlGenerator, $appManager, $iAvatarManager);

		$iUser = $this->createMock(IUser::class);
		$iUser->expects($this->once())
			->method('canChangeDisplayName')
			->willReturn(true);
		$userManager->method('get')->willReturn($iUser);
		$iUser->method('getUID')->willReturn($id);
		$userSession->expects($this->any())
			->method('getUser')
			->willReturn($iUser);

		$iMailer->expects($this->once())->method('validateMailAddress')
			->willReturn(true);
		$mailMessage = $this->createMock(Message::class);
		$iMailer->expects($this->once())
			->method('createMessage')
			->willReturn($mailMessage);
		$iL10->expects($this->atLeastOnce())
			->method('t')
			->willReturn('An email has been sent to this address for confirmation. Until the email is verified this address will not be set.');
		$expectedResponse = new DataResponse(
			[
				'status' => 'success',
				'data' => [
					'username' => $id,
					'mailAddress' => $mailaddress,
					'message' => 'An email has been sent to this address for confirmation. Until the email is verified this address will not be set.'
				]
			],
			Http::STATUS_OK
		);
		$response = $userController->setMailAddress($id, $mailaddress);
		$this->assertEquals($expectedResponse, $response);
	}

	/**
	 * @return array
	 */
	public function setEmailAddressData() {
		return [
			/* mailAddress,    isValid, expectsUpdate, canChangeDisplayName, responseCode */
			[ '',              true,    true,          true,                 Http::STATUS_OK ],
			[ 'foo@local',     true,    true,          true,                 Http::STATUS_OK],
			[ 'foo@bar@local', false,   false,         true,                 Http::STATUS_UNPROCESSABLE_ENTITY],
			[ 'foo@local',     true,    false,         false,                Http::STATUS_FORBIDDEN],
		];
	}

	/**
	 * @dataProvider setEmailAddressData
	 *
	 * @param string $mailAddress
	 * @param bool $isValid
	 * @param bool $expectsUpdate
	 * @param bool $canChangeDisplayName
	 * @param bool $responseCode
	 */
	public function testSetEmailAddress($mailAddress, $isValid, $expectsUpdate, $canChangeDisplayName, $responseCode) {
		$this->groupManager
			->expects($this->any())
			->method('isAdmin')
			->will($this->returnValue(true));

		$user = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$user
			->expects($this->any())
			->method('getUID')
			->will($this->returnValue('foo'));
		$user
			->expects($this->any())
			->method('getEMailAddress')
			->will($this->returnValue('foo@local'));
		$user
			->expects($this->any())
			->method('canChangeDisplayName')
			->will($this->returnValue($canChangeDisplayName));
		$user
			->expects($this->any())
			->method('setEMailAddress')
			->with(
				$this->equalTo($mailAddress)
			);

		$this->userSession
			->expects($this->atLeastOnce())
			->method('getUser')
			->will($this->returnValue($user));
		$this->mailer
			->expects($this->any())
			->method('validateMailAddress')
			->with($mailAddress)
			->willReturn($isValid);

		if ($isValid) {
			$user->expects($this->atLeastOnce())
				->method('canChangeDisplayName')
				->willReturn(true);
		}

		$this->config
			->expects($this->any())
			->method('getUserValue')
			->with('foo', 'owncloud', 'changeMail')
			->will($this->returnValue('12000:AVerySecretToken'));
		$this->timeFactory
			->expects($this->any())
			->method('getTime')
			->willReturnOnConsecutiveCalls(12301, 12348);
		$this->userManager
			->expects($this->atLeastOnce())
			->method('get')
			->with('foo')
			->will($this->returnValue($user));
		$this->secureRandom
			->expects($this->any())
			->method('generate')
			->with('21')
			->will($this->returnValue('ThisIsMaybeANotSoSecretToken!'));
		$this->config
			->expects($this->any())
			->method('setUserValue')
			->with('foo', 'owncloud', 'changeMail', '12348:ThisIsMaybeANotSoSecretToken!');
		$this->urlGenerator
			->expects($this->any())
			->method('linkToRouteAbsolute')
			->will($this->returnValue('https://ownCloud.com/index.php/mailaddress/'));

		$message = $this->getMockBuilder(Message::class)
			->disableOriginalConstructor()->getMock();
		$message
			->expects($this->any())
			->method('setTo')
			->with(['foo@local' => 'foo']);
		$message
			->expects($this->any())
			->method('setSubject')
			->with(' email address confirm');
		$message
			->expects($this->any())
			->method('setPlainBody')
			->with('Use the following link to confirm your changes to the email address: https://ownCloud.com/index.php/mailaddress/');
		$message
			->expects($this->any())
			->method('setFrom')
			->with(['changemail-noreply@localhost' => null]);
		$this->mailer
			->expects($this->any())
			->method('createMessage')
			->will($this->returnValue($message));
		$this->mailer
			->expects($this->any())
			->method('send')
			->with($message);

		$response = $this->createController()->setMailAddress($user->getUID(), $mailAddress);
		$this->assertSame($responseCode, $response->getStatus());
	}

	public function testStatsAdmin() {
		$this->groupManager
			->expects($this->any())
			->method('isAdmin')
			->will($this->returnValue(true));

		$user = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$user->expects($this->any())
			->method('getUID')
			->will($this->returnValue('user'));
		$this->userSession
			->expects($this->any())
			->method('getUser')
			->will($this->returnValue($user));

		$this->userManager
			->expects($this->at(0))
			->method('countUsers')
			->will($this->returnValue([128, 44]));

		$expectedResponse = new DataResponse(
			[
				'totalUsers' => 172
			]
		);
		$response = $this->createController()->stats();
		$this->assertEquals($expectedResponse, $response);
	}

	/**
	 * Tests that the subadmin stats return unique users, even
	 * when a user appears in several groups.
	 */
	public function testStatsSubAdmin() {
		$this->groupManager
			->expects($this->any())
			->method('isAdmin')
			->will($this->returnValue(false));

		$user = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$user->expects($this->any())
			->method('getUID')
			->will($this->returnValue('user'));
		$this->userSession
			->expects($this->any())
			->method('getUser')
			->will($this->returnValue($user));

		$group1 = $this->getMockBuilder(Group::class)
			->disableOriginalConstructor()->getMock();
		$group1
			->expects($this->once())
			->method('getUsers')
			->will($this->returnValue(['foo' => 'M. Foo', 'admin' => 'S. Admin']));

		$group2 = $this->getMockBuilder(Group::class)
			->disableOriginalConstructor()->getMock();
		$group2
			->expects($this->once())
			->method('getUsers')
			->will($this->returnValue(['bar' => 'B. Ar']));

		$subadmin = $this->getMockBuilder(SubAdmin::class)
			->disableOriginalConstructor()
			->getMock();
		$subadmin
			->expects($this->at(0))
			->method('getSubAdminsGroups')
			->will($this->returnValue([$group1, $group2]));

		$this->groupManager
			->expects($this->any())
			->method('getSubAdmin')
			->will($this->returnValue($subadmin));

		$expectedResponse = new DataResponse(
			[
				'totalUsers' => 3
			]
		);

		$response = $this->createController()->stats();
		$this->assertEquals($expectedResponse, $response);
	}

	public function testSetDisplayNameNull() {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('userName');

		$this->userSession
			->expects($this->any())
			->method('getUser')
			->willReturn($user);

		$expectedResponse = new DataResponse(
			[
				'status' => 'error',
				'data' => [
					'message' => 'Authentication error',
				],
			]
		);
		$response = $this->createController()->setDisplayName(null, 'displayName');

		$this->assertEquals($expectedResponse, $response);
	}

	public function dataSetDisplayName() {
		$data = [];

		$user1 = $this->createMock(IUser::class);
		$user1->method('getUID')->willReturn('user1');
		$user1->method('canChangeDisplayName')->willReturn(true);
		$data[] = [$user1, $user1, false, false, true];

		$user1 = $this->createMock(IUser::class);
		$user1->method('getUID')->willReturn('user1');
		$user1->method('canChangeDisplayName')->willReturn(false);
		$data[] = [$user1, $user1, false, false, false];

		$user1 = $this->createMock(IUser::class);
		$user1->method('getUID')->willReturn('user1');
		$user2 = $this->createMock(IUser::class);
		$user2->method('getUID')->willReturn('user2');
		$user2->method('canChangeDisplayName')->willReturn(true);
		$data[] = [$user1, $user2, false, false, false];

		$user1 = $this->createMock(IUser::class);
		$user1->method('getUID')->willReturn('user1');
		$user2 = $this->createMock(IUser::class);
		$user2->method('getUID')->willReturn('user2');
		$user2->method('canChangeDisplayName')->willReturn(true);
		$data[] = [$user1, $user2, true, false, true];

		$user1 = $this->createMock(IUser::class);
		$user1->method('getUID')->willReturn('user1');
		$user2 = $this->createMock(IUser::class);
		$user2->method('getUID')->willReturn('user2');
		$user2->method('canChangeDisplayName')->willReturn(true);
		$data[] = [$user1, $user2, false, true, true];

		return $data;
	}

	/**
	 * @dataProvider dataSetDisplayName
	 */
	public function testSetDisplayName($currentUser, $editUser, $isAdmin, $isSubAdmin, $valid) {
		$this->userSession
			->expects($this->any())
			->method('getUser')
			->willReturn($currentUser);
		$this->userManager
			->expects($this->once())
			->method('get')
			->with($editUser->getUID())
			->willReturn($editUser);

		$subadmin = $this->getMockBuilder(SubAdmin::class)
			->disableOriginalConstructor()
			->getMock();
		$subadmin
			->method('isUserAccessible')
			->with($currentUser, $editUser)
			->willReturn($isSubAdmin);

		$this->groupManager
			->method('getSubAdmin')
			->willReturn($subadmin);
		$this->groupManager
			->method('isAdmin')
			->with($currentUser->getUID())
			->willReturn($isAdmin);

		if ($valid === true) {
			$editUser->expects($this->once())
				->method('setDisplayName')
				->with('newDisplayName')
				->willReturn(true);
			$expectedResponse = new DataResponse(
				[
					'status' => 'success',
					'data' => [
						'message' => 'Your full name has been changed.',
						'username' => $editUser->getUID(),
						'displayName' => 'newDisplayName',
					],
				]
			);
		} else {
			$editUser->expects($this->never())->method('setDisplayName');
			$expectedResponse = new DataResponse(
				[
					'status' => 'error',
					'data' => [
						'message' => 'Authentication error',
					],
				]
			);
		}

		$response = $this->createController()->setDisplayName($editUser->getUID(), 'newDisplayName');
		$this->assertEquals($expectedResponse, $response);
	}

	public function testSetDisplayNameFails() {
		$user = $this->createMock(IUser::class);
		$user->method('canChangeDisplayname')->willReturn(true);
		$user->method('getUID')->willReturn('user');
		$user->expects($this->once())
			->method('setDisplayName')
			->with('newDisplayName')
			->willReturn(false);
		$user->method('getDisplayName')->willReturn('oldDisplayName');

		$this->userSession
			->expects($this->any())
			->method('getUser')
			->willReturn($user);
		$this->userManager
			->expects($this->once())
			->method('get')
			->with($user->getUID())
			->willReturn($user);

		$subadmin = $this->getMockBuilder(SubAdmin::class)
			->disableOriginalConstructor()
			->getMock();
		$subadmin
			->method('isUserAccessible')
			->with($user, $user)
			->willReturn(false);

		$this->groupManager
			->method('getSubAdmin')
			->willReturn($subadmin);
		$this->groupManager
			->expects($this->any())
			->method('isAdmin')
			->with($user->getUID())
			->willReturn(false);

		$expectedResponse = new DataResponse(
			[
				'status' => 'error',
				'data' => [
					'message' => 'Unable to change full name',
					'displayName' => 'oldDisplayName',
				],
			]
		);
		$response = $this->createController()->setDisplayName($user->getUID(), 'newDisplayName');
		$this->assertEquals($expectedResponse, $response);
	}

	public function testDifferentLoggedUserAndRequestUser() {
		$token = 'AVerySecretToken';
		$userId = 'ExistingUser';
		$mailAddress = 'sample@email.com';
		$userObject = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()->getMock();
		$diffUserObject = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()->getMock();

		$this->userManager
			->expects($this->once())
			->method('get')
			->with($userId)
			->will($this->returnValue($userObject));
		$this->userSession
			->expects($this->any())
			->method('getUser')
			->will($this->returnValue($diffUserObject));
		$this->logger
			->expects($this->once())
			->method('error')
			->with('The logged in user is different than expected.');

		$expectedResponse = new RedirectResponse(
			$this->urlGenerator->linkToRoute('settings.SettingsPage.getPersonal', ['changestatus' => 'error'])
		);

		$response = $this->createController()->changeMail($token, $userId, $mailAddress);
		$this->assertEquals($expectedResponse, $response);
	}

	public function testSameLoggedUserAndRequestUser() {
		$token = 'AVerySecretToken';
		$userId = 'ExistingUser';
		$mailAddress = 'sample@email.com';
		$userObject = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()->getMock();

		$this->userManager
			->expects($this->atLeastOnce())
			->method('get')
			->with($userId)
			->will($this->returnValue($userObject));
		$this->userSession
			->expects($this->any())
			->method('getUser')
			->will($this->returnValue($userObject));

		$expectedResponse = new RedirectResponse(
			$this->urlGenerator->linkToRoute('settings.SettingsPage.getPersonal', ['changestatus' => 'success', 'user' => $userId])
		);

		$response = $this->createController()->changeMail($token, $userId, $mailAddress);
		$this->assertEquals($expectedResponse, $response);
	}

	public function testInvalidEmailChangeToken() {
		$token = 'AVerySecretToken';
		$userId = 'ExistingUser';
		$mailAddress = 'sample@email.com';
		$userObject = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()->getMock();

		$this->userManager
			->expects($this->atLeastOnce())
			->method('get')
			->with($userId)
			->will($this->returnValue($userObject));
		$this->userSession
			->expects($this->any())
			->method('getUser')
			->will($this->returnValue($userObject));
		$this->logger
			->expects($this->once())
			->method('error')
			->with('Couldn\'t change the email address because the token is invalid');


		$expectedResponse = new RedirectResponse(
			$this->urlGenerator->linkToRoute('settings.SettingsPage.getPersonal', ['changestatus' => 'error'])
		);

		$response = $this->createController()->changeMail($token, $userId, $mailAddress);
		$this->assertEquals($expectedResponse, $response);
	}

	public function testDisableSelfAdmin() {
		$this->groupManager
			->expects($this->any())
			->method('isAdmin')
			->will($this->returnValue(true));

		$user = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$user
			->expects($this->any())
			->method('getUID')
			->will($this->returnValue('myself'));
		$this->userSession
			->method('getUser')
			->will($this->returnValue($user));
		$expectedResponse = new DataResponse(
			[
				'status' => 'error',
				'data' => [
					'message' => 'Forbidden'
				]
			],
			Http::STATUS_FORBIDDEN
		);
		$response = $this->createController()->setEnabled('myself', 'false');
		$this->assertEquals($expectedResponse, $response);
	}

	public function testEnableSelfAdmin() {
		$this->groupManager
			->expects($this->any())
			->method('isAdmin')
			->will($this->returnValue(true));

		$user = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$user
			->expects($this->any())
			->method('getUID')
			->will($this->returnValue('myself'));
		$this->userSession
			->method('getUser')
			->will($this->returnValue($user));
		$expectedResponse = new DataResponse(
			[
				'status' => 'error',
				'data' => [
					'message' => 'Forbidden'
				]
			],
			Http::STATUS_FORBIDDEN
		);
		$response = $this->createController()->setEnabled('myself', 'true');
		$this->assertEquals($expectedResponse, $response);
	}

	public function testDisableSelfSubadmin() {
		$this->groupManager
			->expects($this->any())
			->method('isAdmin')
			->will($this->returnValue(false));

		$user = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$user
			->expects($this->any())
			->method('getUID')
			->will($this->returnValue('myself'));
		$this->userSession
			->method('getUser')
			->will($this->returnValue($user));
		$expectedResponse = new DataResponse(
			[
				'status' => 'error',
				'data' => [
					'message' => 'Forbidden'
				]
			],
			Http::STATUS_FORBIDDEN
		);
		$response = $this->createController()->setEnabled('myself', 'false');
		$this->assertEquals($expectedResponse, $response);
	}

	public function testEnableSelfSubadmin() {
		$this->groupManager
			->expects($this->any())
			->method('isAdmin')
			->will($this->returnValue(false));

		$user = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$user
			->expects($this->any())
			->method('getUID')
			->will($this->returnValue('myself'));
		$this->userSession
			->method('getUser')
			->will($this->returnValue($user));
		$expectedResponse = new DataResponse(
			[
				'status' => 'error',
				'data' => [
					'message' => 'Forbidden'
				]
			],
			Http::STATUS_FORBIDDEN
		);

		$response = $this->createController()->setEnabled('myself', 'true');
		$this->assertEquals($expectedResponse, $response);
	}

	public function testDisableAdmin() {
		$this->groupManager
			->expects($this->any())
			->method('isAdmin')
			->will($this->returnValue(true));

		$user = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$user
			->expects($this->any())
			->method('getUID')
			->will($this->returnValue('Admin'));
		$toDisableUser = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$this->userSession
			->method('getUser')
			->will($this->returnValue($user));
		$this->userManager
			->method('get')
			->with('UserToDisable')
			->will($this->returnValue($toDisableUser));
		$expectedResponse = new DataResponse(
			[
				'status' => 'success',
				'data' => [
					'username' => 'UserToDisable',
					'enabled' => 'false'
				]
			],
			Http::STATUS_OK
		);
		$response = $this->createController()->setEnabled('UserToDisable', 'false');
		$this->assertEquals($expectedResponse, $response);
	}

	public function testEnableAdmin() {
		$this->groupManager
			->expects($this->any())
			->method('isAdmin')
			->will($this->returnValue(true));

		$user = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$user
			->expects($this->any())
			->method('getUID')
			->will($this->returnValue('Admin'));
		$toEnableUser = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$this->userSession
			->method('getUser')
			->will($this->returnValue($user));
		$this->userManager
			->method('get')
			->with('UserToEnable')
			->will($this->returnValue($toEnableUser));
		$expectedResponse = new DataResponse(
			[
				'status' => 'success',
				'data' => [
					'username' => 'UserToEnable',
					'enabled' => 'true'
				]
			],
			Http::STATUS_OK
		);
		$response = $this->createController()->setEnabled('UserToEnable', 'true');
		$this->assertEquals($expectedResponse, $response);
	}

	public function testDisableSubAdmin() {
		$this->groupManager
			->expects($this->any())
			->method('isAdmin')
			->will($this->returnValue(false));

		$user = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$user
			->expects($this->any())
			->method('getUID')
			->will($this->returnValue('myself'));
		$this->userSession
			->method('getUser')
			->will($this->returnValue($user));
		$user = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$toDisableUser = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$this->userSession
			->method('getUser')
			->will($this->returnValue($user));
		$this->userManager
			->method('get')
			->with('UserToDisable')
			->will($this->returnValue($toDisableUser));
		$subadmin = $this->getMockBuilder(SubAdmin::class)
			->disableOriginalConstructor()
			->getMock();
		$subadmin->expects($this->once())
			->method('isUserAccessible')
			->with($user, $toDisableUser)
			->will($this->returnValue(true));
		$this->groupManager
			->expects($this->any())
			->method('getSubAdmin')
			->will($this->returnValue($subadmin));
		$expectedResponse = new DataResponse(
			[
				'status' => 'success',
				'data' => [
					'username' => 'UserToDisable',
					'enabled' => 'false'
				]
			],
			Http::STATUS_OK
		);
		$response = $this->createController()->setEnabled('UserToDisable', 'false');
		$this->assertEquals($expectedResponse, $response);
	}

	public function testEnableSubAdmin() {
		$this->groupManager
			->expects($this->any())
			->method('isAdmin')
			->will($this->returnValue(false));

		$user = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$user
			->expects($this->any())
			->method('getUID')
			->will($this->returnValue('myself'));
		$this->userSession
			->method('getUser')
			->will($this->returnValue($user));
		$user = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$toEnableUser = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$this->userSession
			->method('getUser')
			->will($this->returnValue($user));
		$this->userManager
			->method('get')
			->with('UserToEnable')
			->will($this->returnValue($toEnableUser));
		$subadmin = $this->getMockBuilder(SubAdmin::class)
			->disableOriginalConstructor()
			->getMock();
		$subadmin->expects($this->once())
			->method('isUserAccessible')
			->with($user, $toEnableUser)
			->will($this->returnValue(true));
		$this->groupManager
			->expects($this->any())
			->method('getSubAdmin')
			->will($this->returnValue($subadmin));
		$expectedResponse = new DataResponse(
			[
				'status' => 'success',
				'data' => [
					'username' => 'UserToEnable',
					'enabled' => 'true'
				]
			],
			Http::STATUS_OK
		);
		$response = $this->createController()->setEnabled('UserToEnable', 'true');
		$this->assertEquals($expectedResponse, $response);
	}

	public function testDisableNotAccessibleToSubAdmin() {
		$this->groupManager
			->expects($this->any())
			->method('isAdmin')
			->will($this->returnValue(false));

		$user = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$user
			->expects($this->any())
			->method('getUID')
			->will($this->returnValue('myself'));
		$this->userSession
			->method('getUser')
			->will($this->returnValue($user));
		$toDisableUser = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$this->userSession
			->method('getUser')
			->will($this->returnValue($user));
		$this->userManager
			->method('get')
			->with('UserToDisable')
			->will($this->returnValue($toDisableUser));
		$subadmin = $this->getMockBuilder(SubAdmin::class)
			->disableOriginalConstructor()
			->getMock();
		$subadmin->expects($this->once())
			->method('isUserAccessible')
			->with($user, $toDisableUser)
			->will($this->returnValue(false));
		$this->groupManager
			->expects($this->any())
			->method('getSubAdmin')
			->will($this->returnValue($subadmin));
		$expectedResponse = new DataResponse(
			[
				'status' => 'error',
				'data' => [
					'message' => 'Forbidden'
				]
			],
			Http::STATUS_FORBIDDEN
		);
		$response = $this->createController()->setEnabled('UserToDisable', 'false');
		$this->assertEquals($expectedResponse, $response);
	}

	public function testEnableNotAccessibleToSubAdmin() {
		$this->groupManager
			->expects($this->any())
			->method('isAdmin')
			->will($this->returnValue(false));

		$user = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$user
			->expects($this->any())
			->method('getUID')
			->will($this->returnValue('myself'));
		$this->userSession
			->method('getUser')
			->will($this->returnValue($user));
		$toEnableUser = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$this->userSession
			->method('getUser')
			->will($this->returnValue($user));
		$this->userManager
			->method('get')
			->with('UserToEnable')
			->will($this->returnValue($toEnableUser));
		$subadmin = $this->getMockBuilder(SubAdmin::class)
			->disableOriginalConstructor()
			->getMock();
		$subadmin->expects($this->once())
			->method('isUserAccessible')
			->with($user, $toEnableUser)
			->will($this->returnValue(false));
		$this->groupManager
			->expects($this->any())
			->method('getSubAdmin')
			->will($this->returnValue($subadmin));
		$expectedResponse = new DataResponse(
			[
				'status' => 'error',
				'data' => [
					'message' => 'Forbidden'
				]
			],
			Http::STATUS_FORBIDDEN
		);
		$response = $this->createController()->setEnabled('UserToEnable', 'true');
		$this->assertEquals($expectedResponse, $response);
	}

	/**
	 * @return UsersController
	 */
	private function createController() {
		return new UsersController(
			'user_management',
			$this->request,
			$this->userManager,
			$this->groupManager,
			$this->userSession,
			$this->config,
			$this->secureRandom,
			$this->l10N,
			$this->logger,
			$this->defaults,
			$this->mailer,
			$this->timeFactory,
			$this->urlGenerator,
			$this->appManager,
			$this->avatarManager
		);
	}
}
