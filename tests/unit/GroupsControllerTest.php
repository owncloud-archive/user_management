<?php
/**
 * @author Lukas Reschke
 * @copyright Copyright (c) 2014 Lukas Reschke lukas@owncloud.com
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OCA\UserManagement\Test\Unit;

use OCA\UserManagement\Controller\GroupsController;
use OCA\UserManagement\MetaData;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;
use OC\Group\Group;
use Test\TestCase;
use OC\User\User;
use OC\User\Session;

/**
 * @package Tests\Settings\Controller
 */
class GroupsControllerTest extends TestCase {

	/** @var GroupsController */
	private $groupsController;
	/** @var IGroupManager | \PHPUnit_Framework_MockObject_MockObject*/
	private $groupManager;
	/** @var IUserSession | \PHPUnit_Framework_MockObject_MockObject */
	private $userSession;
	/** @var IL10N | \PHPUnit_Framework_MockObject_MockObject */
	private $l10N;

	protected function setUp() {
		$this->groupManager = $this->getMockBuilder(IGroupManager::class)
			->disableOriginalConstructor()->getMock();
		$this->groupManager
			->expects($this->any())
			->method('isAdmin')
			->will($this->returnValue(true));

		$this->userSession = $this->getMockBuilder(Session::class)
			->disableOriginalConstructor()->getMock();

		$this->l10N = $this->getMockBuilder(IL10N::class)
			->disableOriginalConstructor()->getMock();
		$this->l10N
			->expects($this->any())
					->method('t')
					->will($this->returnCallback(function($text, $parameters = []) {
							return vsprintf($text, $parameters);
					}));

		$request = $this->createMock(IRequest::class);
		$this->groupsController = new GroupsController(
			'user_management',
			$request,
			$this->groupManager,
			$this->userSession,
			$this->l10N
		);
	}

	/**
	 * TODO: Since GroupManager uses the static OC_Subadmin class it can't be mocked
	 * to test for subadmins. Thus the test always assumes you have admin permissions...
	 */
	public function testIndexSortByName() {
		$firstGroup = $this->getMockBuilder(Group::class)
			->disableOriginalConstructor()->getMock();
		$firstGroup
			->method('getGID')
			->will($this->returnValue('firstGroup'));
		$firstGroup
			->method('count')
			->will($this->returnValue(12));
		$secondGroup = $this->getMockBuilder(Group::class)
			->disableOriginalConstructor()->getMock();
		$secondGroup
			->method('getGID')
			->will($this->returnValue('secondGroup'));
		$secondGroup
			->method('count')
			->will($this->returnValue(25));
		$thirdGroup = $this->getMockBuilder(Group::class)
			->disableOriginalConstructor()->getMock();
		$thirdGroup
			->method('getGID')
			->will($this->returnValue('thirdGroup'));
		$thirdGroup
			->method('count')
			->will($this->returnValue(14));
		$fourthGroup = $this->getMockBuilder(Group::class)
			->disableOriginalConstructor()->getMock();
		$fourthGroup
			->method('getGID')
			->will($this->returnValue('admin'));
		$fourthGroup
			->method('count')
			->will($this->returnValue(18));
		/** @var \OC\Group\Group[] $groups */
		$groups = [];
		$groups[] = $firstGroup;
		$groups[] = $secondGroup;
		$groups[] = $thirdGroup;
		$groups[] = $fourthGroup;

		$user = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$this->userSession
			->expects($this->any())
			->method('getUser')
			->will($this->returnValue($user));
		$user->expects($this->any())
			->method('getUID')
			->will($this->returnValue('MyAdminUser'));
		$this->groupManager
			->method('search')
			->will($this->returnValue($groups));

		$expectedResponse = new DataResponse(
			[
				'data' => [
				'adminGroups' => [
					0 => [
						'id' => 'admin',
						'name' => 'admin',
						'usercount' => 0,//User count disabled 18,
					]
				],
				'groups' =>
					[
						0 => [
							'id' => 'firstGroup',
							'name' => 'firstGroup',
							'usercount' => 0,//User count disabled 12,
						],
						1 => [
							'id' => 'secondGroup',
							'name' => 'secondGroup',
							'usercount' => 0,//User count disabled 25,
						],
						2 => [
							'id' => 'thirdGroup',
							'name' => 'thirdGroup',
							'usercount' => 0,//User count disabled 14,
						],
					]
				]
			]
		);
		$response = $this->groupsController->index('', false, MetaData::SORT_GROUPNAME);
		$this->assertEquals($expectedResponse, $response);
	}

	/**
	 * TODO: Since GroupManager uses the static OC_Subadmin class it can't be mocked
	 * to test for subadmins. Thus the test always assumes you have admin permissions...
	 */
	public function testIndexSortbyCount() {
		$firstGroup = $this->getMockBuilder(Group::class)
			->disableOriginalConstructor()->getMock();
		$firstGroup
			->method('getGID')
			->will($this->returnValue('firstGroup'));
		$firstGroup
			->method('count')
			->will($this->returnValue(12));
		$secondGroup = $this->getMockBuilder(Group::class)
			->disableOriginalConstructor()->getMock();
		$secondGroup
			->method('getGID')
			->will($this->returnValue('secondGroup'));
		$secondGroup
			->method('count')
			->will($this->returnValue(25));
		$thirdGroup = $this->getMockBuilder(Group::class)
			->disableOriginalConstructor()->getMock();
		$thirdGroup
			->method('getGID')
			->will($this->returnValue('thirdGroup'));
		$thirdGroup
			->method('count')
			->will($this->returnValue(14));
		$fourthGroup = $this->getMockBuilder(Group::class)
			->disableOriginalConstructor()->getMock();
		$fourthGroup
			->method('getGID')
			->will($this->returnValue('admin'));
		$fourthGroup
			->method('count')
			->will($this->returnValue(18));
		/** @var \OC\Group\Group[] $groups */
		$groups = [];
		$groups[] = $firstGroup;
		$groups[] = $secondGroup;
		$groups[] = $thirdGroup;
		$groups[] = $fourthGroup;

		$user = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$this->userSession
			->expects($this->any())
			->method('getUser')
			->will($this->returnValue($user));
		$user
			->expects($this->any())
			->method('getUID')
			->will($this->returnValue('MyAdminUser'));
		$this->groupManager
			->method('search')
			->will($this->returnValue($groups));

		$expectedResponse = new DataResponse(
			[
				'data' => [
				'adminGroups' => [
					0 => [
						'id' => 'admin',
						'name' => 'admin',
						'usercount' => 18,
					]
				],
				'groups' =>
					[
						0 => [
							'id' => 'secondGroup',
							'name' => 'secondGroup',
							'usercount' => 25,
						],
						1 => [
							'id' => 'thirdGroup',
							'name' => 'thirdGroup',
							'usercount' => 14,
						],
						2 => [
							'id' => 'firstGroup',
							'name' => 'firstGroup',
							'usercount' => 12,
						],
					]
				]
			]
		);
		$response = $this->groupsController->index();
		$this->assertEquals($expectedResponse, $response);
	}

	public function testCreateWithExistingGroup() {
		$this->groupManager
			->expects($this->once())
			->method('groupExists')
			->with('ExistingGroup')
			->will($this->returnValue(true));

		$expectedResponse = new DataResponse(
			[
				'message' => 'Group already exists.'
			],
			Http::STATUS_CONFLICT
		);
		$response = $this->groupsController->create('ExistingGroup');
		$this->assertEquals($expectedResponse, $response);
	}

	public function testCreateSuccessful() {
		$this->groupManager
			->expects($this->once())
			->method('groupExists')
			->with('NewGroup')
			->will($this->returnValue(false));
		$this->groupManager
			->expects($this->once())
			->method('createGroup')
			->with('NewGroup')
			->will($this->returnValue(true));

		$expectedResponse = new DataResponse(
			[
				'groupname' => 'NewGroup'
			],
			Http::STATUS_CREATED
		);
		$response = $this->groupsController->create('NewGroup');
		$this->assertEquals($expectedResponse, $response);
	}

	public function testCreateUnsuccessful() {
		$this->groupManager
			->expects($this->once())
			->method('groupExists')
			->with('NewGroup')
			->will($this->returnValue(false));
		$this->groupManager
			->expects($this->once())
			->method('createGroup')
			->with('NewGroup')
			->will($this->returnValue(false));

		$expectedResponse = new DataResponse(
			[
				'status' => 'error',
				'data' => ['message' => 'Unable to add group.']
			],
			Http::STATUS_FORBIDDEN
		);
		$response = $this->groupsController->create('NewGroup');
		$this->assertEquals($expectedResponse, $response);
	}

	public function destroyData() {
		return [
			['ExistingGroup'],
			['a/b'],
			['a*2&&bc//f!!'],
			['!c@\\$%^&*()|2']
		];
	}

	/**
	 * Test destroy works with special characters also
	 * @dataProvider destroyData
	 */
	public function testDestroySuccessful($groupName) {
		$group = $this->getMockBuilder(Group::class)
			->disableOriginalConstructor()->getMock();
		$this->groupManager
			->expects($this->once())
			->method('get')
			->with($groupName)
			->will($this->returnValue($group));
		$group
			->expects($this->once())
			->method('delete')
			->will($this->returnValue(true));

		$expectedResponse = new DataResponse(
			[
				'status' => 'success',
				'data' => ['groupname' => $groupName]
			],
			Http::STATUS_NO_CONTENT
		);
		$response = $this->groupsController->destroy($groupName);
		$this->assertEquals($expectedResponse, $response);
	}

	public function testDestroyUnsuccessful() {
		$this->groupManager
			->expects($this->once())
			->method('get')
			->with('ExistingGroup')
			->will($this->returnValue(null));

		$expectedResponse = new DataResponse(
			[
				'status' => 'error',
				'data' => ['message' => 'Unable to delete group.']
			],
			Http::STATUS_FORBIDDEN
		);
		$response = $this->groupsController->destroy('ExistingGroup');
		$this->assertEquals($expectedResponse, $response);
	}

}
