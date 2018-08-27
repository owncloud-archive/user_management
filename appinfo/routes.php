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

$this->create('settings_ajax_togglegroups', '/ajax/togglegroups.php')
	->post()
	->actionInclude('user_management/ajax/togglegroups.php');
$this->create('settings_ajax_togglesubadmins', '/ajax/togglesubadmins.php')
	->post()
	->actionInclude('user_management/ajax/togglesubadmins.php');

return [
	'resources' => [
		'groups' => ['url' => '/groups'],
		'users' => ['url' => '/users'],
	],
	'routes' => [
		// page
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
		// users controller
		['name' => 'Users#setDisplayName', 'url' => '/users/{username}/displayName', 'verb' => 'POST'],
		['name' => 'Users#setMailAddress', 'url' => '/users/{id}/mailAddress', 'verb' => 'PUT'],
		['name' => 'Users#setEmailAddress', 'url' => '/admin/{id}/mailAddress', 'verb' => 'PUT'],
		['name' => 'Users#setEnabled', 'url' => '/users/{id}/enabled', 'verb' => 'POST'],
		['name' => 'Users#stats', 'url' => '/users/stats', 'verb' => 'GET'],
		['name' => 'ChangePassword#changePassword', 'url' => '/users/changepassword', 'verb' => 'POST'],
		['name' => 'Users#setPasswordForm', 'url' => '/setpassword/form/{token}/{userId}', 'verb' => 'GET'],
		['name' => 'Users#resendToken', 'url' => '/resend/token/{userId}', 'verb' => 'POST'],
		['name' => 'Users#setPassword', 'url' => '/setpassword/{token}/{userId}', 'verb' => 'POST'],
	]
];
