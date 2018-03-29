<?php
/**
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

namespace OCA\UserManagement\AppInfo;


use OCA\UserManagement\SubadminMiddleware;
use OCP\AppFramework\App;
use OCP\IContainer;

class Application extends App {

	function __construct(array $urlParams = []) {
		parent::__construct('user_management', $urlParams);

		/**
		 * Middleware
		 */
		$this->getContainer()
			->registerService('SubadminMiddleware', function(IContainer $c){
				return 	$c->query(SubadminMiddleware::class);
			});
		// Execute middlewares
		$this->getContainer()->registerMiddleWare('SubadminMiddleware');
	}

}