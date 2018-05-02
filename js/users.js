/**
 * Copyright (c) 2014, Arthur Schiwon <blizzz@owncloud.com>
 * Copyright (c) 2014, Raghu Nayyar <beingminimal@gmail.com>
 * Copyright (c) 2011, Robin Appelman <icewind1991@gmail.com>
 * This file is licensed under the Affero General Public License version 3 or later.
 * See the COPYING-README file.
 */

var $userList;
var $userListBody;

var UserDeleteHandler;
var UserList = {
	availableGroups: [],
	offset: 0,
	usersToLoad: 200,
	initialUsersToLoad: 200, // initial number of users to load
	perPageUsersToLoad: 100, // users to load when user scrolls down
	currentUserId: '',
	currentGid: '',
	filter: '',

	/**
	 * Initializes the user list
	 * @param $el user list table element
	 */
	initialize: function($el) {
		this.$el = $el;

		UserList.currentUserId = OC.getCurrentUser().uid;

		// initially the list might already contain user entries (not fully ajaxified yet)
		// initialize these entries
		this.$el.find('.isEnabled').on('change', this.onEnabledChange);
		this.$el.find('.quota-user').singleSelect().on('change', this.onQuotaSelect);
	},

	/**
	 * Add a user row from user object
	 *
	 * @param user object containing following keys:
	 * 			{
	 * 				'userId': 			'user id',
	 * 				'userName': 		'user name',
	 * 				'displayName': 		'Users display name',
	 * 				'groups': 			['group1', 'group2'],
	 * 				'subadmin': 		['group4', 'group5'],
	 *				'enabled'			'true'
	 *				'quota': 			'10 GB',
	 *				'storageLocation':	'/srv/www/owncloud/data/username',
	 *				'lastLogin':		'1418632333'
	 *				'backend':			'LDAP',
	 *				'email':			'username@example.org'
	 *				'isRestoreDisabled':false
	 * 			}
	 * @param sort
	 * @returns table row created for this user
	 */
	add: function (user, sort) {
		if (this.currentGid && this.currentGid !== '_everyone' && _.indexOf(user.groups, this.currentGid) < 0) {
			return;
		}

		var $tr = $userListBody.find('tr:first-child').clone();
		// this removes just the `display:none` of the template row
		$tr.removeAttr('style');

		/**
		 * Avatar or placeholder
		 */
		if ($tr.find('div.avatardiv').length) {
			if (user.isAvatarAvailable === true) {
				$('div.avatardiv', $tr).avatar(user.userId, 32, undefined, undefined, undefined, user.displayName);
			} else {
				$('div.avatardiv', $tr).imageplaceholder(user.displayName, undefined, 32);
			}
		}

		/**
		 * add metadata to row (in data and visible markup)
		 */
		$tr.data('userId', user.userId);
		$tr.data('userName', user.userName);
		$tr.data('displayName', user.displayName);
		$tr.data('mailAddress', user.email);
		$tr.data('restoreDisabled', user.isRestoreDisabled);
		$tr.find('td.userId').text(user.userId);
		$tr.find('th.userName > span').text(user.userName);
		$tr.find('td.displayName > span').text(user.displayName);
		$tr.find('td.mailAddress > span').text(user.email);
		$tr.find('td.displayName > .action').tooltip({placement: 'top'});
		$tr.find('td.mailAddress > .action').tooltip({placement: 'top'});
		$tr.find('td.password > .action').tooltip({placement: 'top'});


		/**
		 * groups and subadmins
		 */
		var $tdGroups = $tr.find('td.groups');
		this._updateGroupListLabel($tdGroups, user.groups);
		$tdGroups.find('.action').tooltip({placement: 'top'});

		var $tdSubadmins = $tr.find('td.subadmins');
		this._updateGroupListLabel($tdSubadmins, user.subadmin);
		$tdSubadmins.find('.action').tooltip({placement: 'top'});

		/**
		 * enabled
		 */
		var $tdEnabled = $tr.find('.isEnabled');
		if(user.userId !== UserList.currentUserId) {
			$tdEnabled.attr("checked", user.isEnabled);
			$tdEnabled.on('change', UserList.onEnabledChange);
		} else {
			$tdEnabled.remove();
		}

		/**
		 * remove action
		 */
		if ($tr.find('td.remove img').length === 0 && UserList.currentUserId !== user.userId) {
			var deleteImage = $('<img class="action">').attr({
				src: OC.imagePath('core', 'actions/delete')
			});
			var deleteLink = $('<a class="action delete">')
				.attr({ href: '#', 'original-title': t('user_management', 'Delete')})
				.append(deleteImage);
			$tr.find('td.remove').append(deleteLink);
		} else if (UserList.currentUserId === user.userId) {
			$tr.find('td.remove a').remove();
		}

		/**
		 * quota
		 */
		var $quotaSelect = $tr.find('.quota-user');
		if (user.quota === 'default') {
			$quotaSelect
				.data('previous', 'default')
				.find('option').attr('selected', null)
				.first().attr('selected', 'selected');
		} else {
			var $options = $quotaSelect.find('option');
			var $foundOption = $options.filterAttr('value', user.quota);
			if ($foundOption.length > 0) {
				$foundOption.attr('selected', 'selected');
			} else {
				// append before "Other" entry
				$options.last().before('<option value="' + escapeHTML(user.quota) + '" selected="selected">' + escapeHTML(user.quota) + '</option>');
			}
		}

		/**
		 * storage location
		 */
		$tr.find('td.storageLocation').text(user.storageLocation);

		/**
		 * user backend
		 */
		$tr.find('td.userBackend').text(user.backend);

		/**
		 * last login
		 */
		var lastLoginRel = t('user_management', 'never');
		var lastLoginAbs = lastLoginRel;
		if(user.lastLogin !== 0) {
			lastLoginRel = OC.Util.relativeModifiedDate(user.lastLogin);
			lastLoginAbs = OC.Util.formatDate(user.lastLogin);
		}
		var $tdLastLogin = $tr.find('td.lastLogin');
		$tdLastLogin.text(lastLoginRel);
		$tdLastLogin.attr('title', lastLoginAbs);
		// setup tooltip with #app-content as container to prevent the td to resize on hover
		$tdLastLogin.tooltip({placement: 'top', container: '#app-content'});

		/**
		 * append generated row to user list
		 */
		$tr.appendTo($userList);
		if(UserList.isEmpty === true) {
			//when the list was emptied, one row was left, necessary to keep
			//add working and the layout unbroken. We need to remove this item
			$tr.show();
			$userListBody.find('tr:first').remove();
			UserList.isEmpty = false;
			UserList.checkUsersToLoad();
		}

		/**
		 * sort list
		 */
		if (sort) {
			UserList.doSort();
		}

		$quotaSelect.on('change', UserList.onQuotaSelect);

		// defer init so the user first sees the list appear more quickly
		window.setTimeout(function(){
			$quotaSelect.singleSelect();
		}, 0);
		return $tr;
	},
	// From http://my.opera.com/GreyWyvern/blog/show.dml/1671288
	alphanum: function(a, b) {
		function chunkify(t) {
			var tz = [], x = 0, y = -1, n = 0, i, j;

			while (i = (j = t.charAt(x++)).charCodeAt(0)) {
				var m = (i === 46 || (i >=48 && i <= 57));
				if (m !== n) {
					tz[++y] = "";
					n = m;
				}
				tz[y] += j;
			}
			return tz;
		}

		var aa = chunkify(a.toLowerCase());
		var bb = chunkify(b.toLowerCase());

		for (var x = 0; aa[x] && bb[x]; x++) {
			if (aa[x] !== bb[x]) {
				var c = Number(aa[x]), d = Number(bb[x]);
				if (c === aa[x] && d === bb[x]) {
					return c - d;
				} else {
					return (aa[x] > bb[x]) ? 1 : -1;
				}
			}
		}
		return aa.length - bb.length;
	},
	preSortSearchString: function(a, b) {
		var pattern = this.filter;
		if(typeof pattern === 'undefined') {
			return undefined;
		}
		pattern = pattern.toLowerCase();
		var aMatches = false;
		var bMatches = false;
		if(typeof a === 'string' && a.toLowerCase().indexOf(pattern) === 0) {
			aMatches = true;
		}
		if(typeof b === 'string' && b.toLowerCase().indexOf(pattern) === 0) {
			bMatches = true;
		}

		if((aMatches && bMatches) || (!aMatches && !bMatches)) {
			return undefined;
		}

		if(aMatches) {
			return -1;
		} else {
			return 1;
		}
	},
	doSort: function() {
		// some browsers like Chrome lose the scrolling information
		// when messing with the list elements
		var lastScrollTop = this.scrollArea.scrollTop();
		var lastScrollLeft = this.scrollArea.scrollLeft();
		var rows = $userListBody.find('tr').get();

		rows.sort(function(a, b) {
			// FIXME: inefficient way of getting the names,
			// better use a data attribute
			a = $(a).find('.userId').text();
			b = $(b).find('.userId').text();
			var firstSort = UserList.preSortSearchString(a, b);
			if(typeof firstSort !== 'undefined') {
				return firstSort;
			}
			return OC.Util.naturalSortCompare(a, b);
		});

		var items = [];
		$.each(rows, function(index, row) {
			items.push(row);
			if(items.length === 100) {
				$userListBody.append(items);
				items = [];
			}
		});
		if(items.length > 0) {
			$userListBody.append(items);
		}
		this.scrollArea.scrollTop(lastScrollTop);
		this.scrollArea.scrollLeft(lastScrollLeft);
	},
	checkUsersToLoad: function() {
		if(UserList.isEmpty === false) {
			UserList.usersToLoad = UserList.perPageUsersToLoad;
		} else {
			UserList.usersToLoad = UserList.initialUsersToLoad;
		}
	},
	empty: function() {
		//one row needs to be kept, because it is cloned to add new rows
		$userListBody.find('tr:not(:first)').remove();
		var $tr = $userListBody.find('tr:first');
		$tr.hide();
		//on an update a user may be missing when the userId matches with that
		//of the hidden row. So change this to a random string.
		$tr.data('userId', Math.random().toString(36).substring(2));
		UserList.isEmpty = true;
		UserList.offset = 0;
		UserList.checkUsersToLoad();
	},
	hide: function(userId) {
		UserList.getRow(userId).hide();
	},
	show: function(userId) {
		UserList.getRow(userId).show();
	},
	markRemove: function(userId) {
		var $tr = UserList.getRow(userId);
		var groups = $tr.find('.groups').data('groups');
		for(var i in groups) {
			var gid = groups[i];
			var $li = GroupList.getGroupLI(gid);
			var userCount = GroupList.getUserCount($li);
			GroupList.setUserCount($li, userCount - 1);
		}
		GroupList.decEveryoneCount();
		UserList.hide(userId);
	},
	remove: function(userId) {
		UserList.getRow(userId).remove();
	},
	undoRemove: function(userId) {
		var $tr = UserList.getRow(userId);
		var groups = $tr.find('.groups').data('groups');
		for(var i in groups) {
			var gid = groups[i];
			var $li = GroupList.getGroupLI(gid);
			var userCount = GroupList.getUserCount($li);
			GroupList.setUserCount($li, userCount + 1);
		}
		GroupList.incEveryoneCount();
		UserList.getRow(userId).show();
	},
	has: function(userId) {
		return UserList.getRow(userId).length > 0;
	},
	getRow: function(userId) {
		return $userListBody.find('tr').filter(function(){
			return UserList.getUserId(this) === userId;
		});
	},
	getUserId: function(element) {
		return ($(element).closest('tr').data('userId') || '').toString();
	},
	getUserName: function(element) {
		return ($(element).closest('tr').data('userName') || '').toString();
	},
	getDisplayName: function(element) {
		return ($(element).closest('tr').data('displayName') || '').toString();
	},
	getMailAddress: function(element) {
		return ($(element).closest('tr').data('mailAddress') || '').toString();
	},
	getRestoreDisabled: function(element) {
		return ($(element).closest('tr').data('restoreDisabled') || '');
	},
	initDeleteHandling: function() {
		//set up handler
		UserDeleteHandler = new DeleteHandler('/apps/user_management/users', 'userId',
											UserList.markRemove, UserList.remove);

		//configure undo
		OC.Notification.hide();
		var msg = escapeHTML(t('user_management', 'deleted {userId}', {userId: '%oid'})) + '<span class="undo">' +
			escapeHTML(t('user_management', 'undo')) + '</span>';
		UserDeleteHandler.setNotification(OC.Notification, 'deleteuser', msg,
										UserList.undoRemove);

		//when to mark user for delete
		$userListBody.on('click', '.delete', function () {
			// Call function for handling delete/undo
			var userId = UserList.getUserId(this);
			UserDeleteHandler.mark(userId);
		});

		//delete a marked user when leaving the page
		$(window).on('beforeunload', function () {
			UserDeleteHandler.deleteEntry();
		});
	},
	update: function (gid, limit) {
		if (UserList.updating) {
			return;
		}
		if(!limit) {
			limit = UserList.usersToLoad;
		}
		$userList.siblings('.loading').css('visibility', 'visible');
		UserList.updating = true;
		if(gid === undefined) {
			gid = '';
		}
		UserList.currentGid = gid;
		var pattern = this.filter;
		$.get(
			OC.generateUrl('/apps/user_management/users'),
			{ offset: UserList.offset, limit: limit, gid: gid, pattern: pattern },
			function (result) {
				var loadedUsers = 0;
				var trs = [];
				//The offset does not mirror the amount of users available,
				//because it is backend-dependent. For correct retrieval,
				//always the limit(requested amount of users) needs to be added.
				$.each(result, function (index, user) {
					if(UserList.has(user.userId)) {
						return true;
					}
					var $tr = UserList.add(user, false);
					trs.push($tr);
					loadedUsers++;
				});
				if (result.length > 0) {
					UserList.doSort();
					$userList.siblings('.loading').css('visibility', 'hidden');
					// reset state on load
					UserList.noMoreEntries = false;
				}
				else {
					UserList.noMoreEntries = true;
					$userList.siblings('.loading').remove();
				}
				UserList.offset += limit;
			}).always(function() {
				UserList.updating = false;
			});
	},

	applyGroupSelect: function (element, userId, checked) {
		var $element = $(element);

		var checkHandler = null;
		if(userId) { // Only if in a user row, and not the #newusergroups select
			checkHandler = function (group) {
				if (userId === UserList.currentUserId && group === 'admin') {
					return false;
				}
				if (!oc_isadmin && checked.length === 1 && checked[0] === group) {
					return false;
				}
				$.post(
					OC.filePath('user_management', 'ajax', 'togglegroups.php'),
					{
						userId: userId,
						group: group
					},
					function (response) {
						if (response.status === 'success') {
							GroupList.update();
							var groupName = response.data.groupname;
							if (UserList.availableGroups.indexOf(groupName) === -1 &&
								response.data.action === 'add'
							) {
								UserList.availableGroups.push(groupName);
							}

							if (response.data.action === 'add') {
								GroupList.incGroupCount(groupName);
							} else {
								GroupList.decGroupCount(groupName);
							}
						}
						if (response.data.message) {
							OC.Notification.show(response.data.message);
						}
					}
				);
			};
		}
		var addGroup = function (select, group) {
			GroupList.addGroup(escapeHTML(group));
		};
		var label;
		if (oc_isadmin) {
			label = t('user_management', 'add group');
		}
		else {
			label = null;
		}
		$element.multiSelect({
			createCallback: addGroup,
			createText: label,
			selectedFirst: true,
			checked: checked,
			oncheck: checkHandler,
			onuncheck: checkHandler,
			minWidth: 100
		});
	},

	applySubadminSelect: function (element, userId, checked) {
		var $element = $(element);
		var checkHandler = function (group) {
			if (group === 'admin') {
				return false;
			}
			$.post(
				OC.filePath('user_management', 'ajax', 'togglesubadmins.php'),
				{
					userId: userId,
					group: group
				},
				function () {
				}
			);
		};

		$element.multiSelect({
			createText: null,
			checked: checked,
			oncheck: checkHandler,
			onuncheck: checkHandler,
			minWidth: 100
		});
	},

	_onScroll: function() {
		if (!!UserList.noMoreEntries) {
			return;
		}
		if (UserList.scrollArea.scrollTop() + UserList.scrollArea.height() > UserList.scrollArea.get(0).scrollHeight - 500) {
			UserList.update(UserList.currentGid);
		}
	},

	/**
	 * Event handler for when a quota has been changed through a single select.
	 * This will save the value.
	 */
	onQuotaSelect: function(ev) {
		var $select = $(ev.target);
		var userId = UserList.getUserId($select);
		var quota = $select.val();
		if (quota === 'other') {
			return;
		}
		if (
			['default', 'none'].indexOf(quota) === -1
			&& (OC.Util.computerFileSize(quota) === null)
		) {
			// the select component has added the bogus value, delete it again
			$select.find('option[selected]').remove();
			OC.Notification.showTemporary(t('user_management', 'Invalid quota value "{val}"', {val: quota}));
			return;
		}
		UserList._updateQuota(userId, quota, function(returnedQuota){
			if (quota !== returnedQuota) {
				$select.find(':selected').text(returnedQuota);
			}
		});
	},

	/**
	 * Saves the quota for the given user
	 * @param {String} [userId] optional user id, sets default quota if empty
	 * @param {String} quota quota value
	 * @param {Function} ready callback after save
	 */
	_updateQuota: function(userId, quota, ready) {
		$.ajax({
			url: OC.linkToOCS('cloud', 2) + 'users/' + encodeURIComponent(userId) + '?format=json',
			/* jshint camelcase: false */
			data: {
				key: 'quota',
				value: quota
			},
			type: 'PUT',
			beforeSend: function(xhr) {
				xhr.setRequestHeader('OCS-APIREQUEST', 'true');
			},
			success: function() {
				if (ready) {
					ready(quota);
				}
			},
			error: function() {
				OC.Notification.showTemporary(t('user_management', 'Error while setting quota.'));
			}
		});
	},

	/**
         * Event handler for when a enabled value has been changed.
         * This will save the value.
         */
        onEnabledChange: function() {
                var $select = $(this);
                var userId = UserList.getUserId($select);
                var enabled = $select.prop('checked') ? 'true' : 'false';

                UserList._updateEnabled(userId, enabled,
                        function(returnedEnabled){
                                if (enabled !== returnedEnabled) {
                                          $select.prop('checked', user.isEnabled);
                                }
                        });
        },


        /**
         * Saves the enabled value for the given user
         * @param {String} [userId] optional user id, sets default quota if empty
         * @param {String} enabled value
         * @param {Function} ready callback after save
         */
        _updateEnabled: function(userId, enabled, ready) {
               $.post(
                        OC.generateUrl('/apps/user_management/{userId}/enabled', {userId: userId}),
                        {userId: userId, enabled: enabled},
                        function (result) {
                               	if(result.status == 'success') {
                                        OC.Notification.showTemporary(t('admin', 'User {userId} has been {state}!',
                                                                        {userId: userId,
                                                                        state: result.data.enabled === 'true' ?
                                                                        t('admin', 'enabled') :
                                                                        t('admin', 'disabled')}
                                                                     ));
				} else {
                                        OC.Notification.showTemporary(t('admin', result.data.message));
				}
                        }
               );
        },


	/**
	 * Creates a temporary jquery.multiselect selector on the given group field
	 */
	_triggerGroupEdit: function($td, isSubadminSelect) {
		var $groupsListContainer = $td.find('.groupsListContainer');
		var placeholder = $groupsListContainer.attr('data-placeholder') || t('user_management', 'no group');
		var user = UserList.getUID($td);
		var checked = $td.data('groups') || [];
		var extraGroups = [].concat(checked);

		$td.find('.multiselectoptions').remove();

		// jquery.multiselect can only work with select+options in DOM ? We'll give jquery.multiselect what it wants...
		var $groupsSelect;
		if (isSubadminSelect) {
			$groupsSelect = $('<select multiple="multiple" class="groupsselect multiselect button" title="' + placeholder + '"></select>');
		} else {
			$groupsSelect = $('<select multiple="multiple" class="subadminsselect multiselect button" title="' + placeholder + '"></select>')
		}

		function createItem(group) {
			if (isSubadminSelect && group === 'admin') {
				// can't become subadmin of "admin" group
				return;
			}
			$groupsSelect.append($('<option value="' + escapeHTML(group) + '">' + escapeHTML(group) + '</option>'));
		}

		$.each(this.availableGroups, function (i, group) {
			// some new groups might be selected but not in the available groups list yet
			var extraIndex = extraGroups.indexOf(group);
			if (extraIndex >= 0) {
				// remove extra group as it was found
				extraGroups.splice(extraIndex, 1);
			}
			createItem(group);
		});
		$.each(extraGroups, function (i, group) {
			createItem(group);
		});

		$td.append($groupsSelect);

		if (isSubadminSelect) {
			UserList.applySubadminSelect($groupsSelect, user, checked);
		} else {
			UserList.applyGroupSelect($groupsSelect, user, checked);
		}

		$groupsListContainer.addClass('hidden');
		$td.find('.multiselect:not(.groupsListContainer):first').click();
		$groupsSelect.on('dropdownclosed', function(e) {
			$groupsSelect.remove();
			$td.find('.multiselect:not(.groupsListContainer)').parent().remove();
			$td.find('.multiselectoptions').remove();
			$groupsListContainer.removeClass('hidden');
			UserList._updateGroupListLabel($td, e.checked);
		});
	},

	/**
	 * Updates the groups list td with the given groups selection
	 */
	_updateGroupListLabel: function($td, groups) {
		var placeholder = $td.find('.groupsListContainer').attr('data-placeholder');
		var $groupsEl = $td.find('.groupsList');
		$groupsEl.text(groups.join(', ') || placeholder || t('user_management', 'no group'));
		$td.data('groups', groups);
	}
};

$(document).ready(function () {
	OC.Plugins.attach('OC.Settings.UserList', UserList);
	$userList = $('#userlist');
	$userListBody = $userList.find('tbody');

	UserList.initDeleteHandling();

	// Implements User Search
	OCA.Search.users= new UserManagementFilter(UserList, GroupList);

	UserList.scrollArea = $('#app-content');

	UserList.doSort();
	UserList.availableGroups = $userList.data('groups');

	UserList.scrollArea.scroll(function(e) {UserList._onScroll(e);});

	$userList.after($('<div class="loading" style="height: 200px; visibility: hidden;"></div>'));

	// TODO: move other init calls inside of initialize
	UserList.initialize($('#userlist'));

	$userListBody.on('click', '.password', function (event) {
		event.stopPropagation();

		var $td = $(this).closest('td');
		var $tr = $(this).closest('tr');
		var userId = UserList.getUserId($td);
		var $input = $('<input type="password">');
		var isRestoreDisabled = UserList.getRestoreDisabled($td) === true;
		if(isRestoreDisabled) {
			$tr.addClass('row-warning');
			// add tipsy if the password change could cause data loss - no recovery enabled
			$input.tipsy({gravity:'s'});
			$input.attr('title', t('user_management', 'Changing the password will result in data loss, because data recovery is not available for this user'));
		}
		$td.find('img').hide();
		$td.children('span').replaceWith($input);
		$input
			.focus()
			.keypress(function (event) {
				if (event.keyCode === 13) {
					if ($(this).val().length > 0) {
						var recoveryPasswordVal = $('input:password[id="recoveryPassword"]').val();
						$.post(
							OC.generateUrl('/apps/user_management/users/changepassword'),
							{userId: userId, password: $(this).val(), recoveryPassword: recoveryPasswordVal},
							function (result) {
								if(result.status === 'success') {
									OC.Notification.showTemporary(t('admin', 'Password successfully changed'));
								} else {
									OC.Notification.showTemporary(t('admin', result.data.message));
								}
							}
						);
						$input.blur();
					} else {
						$input.blur();
					}
				}
			})
			.blur(function () {
				$(this).replaceWith($('<span>●●●●●●●</span>'));
				$td.find('img').show();
				// remove highlight class from users without recovery ability
				$tr.removeClass('row-warning');
			});
	});
	$('input:password[id="recoveryPassword"]').keyup(function() {
		OC.Notification.hide();
	});

	$userListBody.on('click', '.userName', function (event) {
		event.stopPropagation();
		var $th = $(this).closest('th');
		var $tr = $th.closest('tr');
		var userId = UserList.getUserId($th);
		var userName = escapeHTML(UserList.getUserName($th));
		var $input = $('<input type="text" value="' + userName + '">');
		$th.find('img').hide();
		$th.children('span').replaceWith($input);
		$input
			.focus()
			.keypress(function (event) {
				if (event.keyCode === 13) {
					if ($(this).val().length > 0) {
						$.post(
							OC.generateUrl('/apps/user_management/users/{userId}/userName', {userId: userId}),
							{userId: userId, userName: $(this).val()},
							function (result) {
								var $div = $tr.find('div.avatardiv');
								if (result && result.status==='success' && $div.length){
									$div.avatar(result.data.displayName, 32);
								}
							}
						);
						var userName = $input.val();
						$tr.data('userName', userName);
						$input.blur();
					} else {
						$input.blur();
					}
				}
			})
			.blur(function () {
				var userName = $tr.data('userName');
				$input.replaceWith('<span>' + escapeHTML(userName) + '</span>');
				$th.find('img').show();
			});
	});

	$userListBody.on('click', '.displayName', function (event) {
		event.stopPropagation();
		var $td = $(this).closest('td');
		var $tr = $td.closest('tr');
		var userId = UserList.getUserId($td);
		var displayName = escapeHTML(UserList.getDisplayName($td));
		var $input = $('<input type="text" value="' + displayName + '">');
		$td.find('img').hide();
		$td.children('span').replaceWith($input);
		$input
			.focus()
			.keypress(function (event) {
				if (event.keyCode === 13) {
					if ($(this).val().length > 0) {
						var $div = $tr.find('div.avatardiv');
						if ($div.length) {
							$div.imageplaceholder(userId, displayName);
						}
						$.post(
							OC.generateUrl('/apps/user_management/users/{userId}/displayName', {userId: userId}),
							{userId: userId, displayName: $(this).val()},
							function (result) {
								if (result && result.status==='success' && $div.length){
									$div.avatar(result.data.userId, 32);
								}
							}
						);
						var displayName = $input.val();
						$tr.data('displayName', displayName);
						$input.blur();
					} else {
						$input.blur();
					}
				}
			})
			.blur(function () {
				var displayName = $tr.data('displayName');
				$input.replaceWith('<span>' + escapeHTML(displayName) + '</span>');
				$td.find('img').show();
			});
	});

	$userListBody.on('click', '.mailAddress', function (event) {
		event.stopPropagation();
		var $td = $(this).closest('td');
		var $tr = $td.closest('tr');
		var userId = UserList.getUserId($td);
		var mailAddress = escapeHTML(UserList.getMailAddress($td));
		var $input = $('<input type="text">').val(mailAddress);
		$td.children('span').replaceWith($input);
		$td.find('img').hide();
		$input
			.focus()
			.keypress(function (event) {
				if (event.keyCode === 13) {
					// enter key

					var mailAddress = $input.val();
					$td.find('.loading-small').css('display', 'inline-block');
					$input.css('padding-right', '26px');
					$input.attr('disabled', 'disabled');
					$.ajax({
						type: 'PUT',
						url: OC.generateUrl('/apps/user_management/admin/{userId}/mailAddress', {userId: userId}),
						data: {
							mailAddress: $(this).val()
						}
					}).success(function () {
						// set data attribute to new value
						// will in blur() be used to show the text instead of the input field
						$tr.data('mailAddress', mailAddress);
						$td.find('.loading-small').css('display', '');
						$input.removeAttr('disabled')
							.triggerHandler('blur'); // needed instead of $input.blur() for Firefox
					}).fail(function (result) {
						OC.Notification.showTemporary(result.responseJSON.data.message);
						$td.find('.loading-small').css('display', '');
						$input.removeAttr('disabled')
							.css('padding-right', '6px');
					});
				}
			})
			.blur(function () {
				if($td.find('.loading-small').css('display') === 'inline-block') {
					// in Chrome the blur event is fired too early by the browser - even if the request is still running
					return;
				}
				var $span = $('<span>').text($tr.data('mailAddress'));
				$input.replaceWith($span);
				$td.find('img').show();
			});
	});

	$('#newuser .groupsListContainer').on('click', function (event) {
		event.stopPropagation();
		var $div = $(this).closest('.groups');
		UserList._triggerGroupEdit($div);
	});
	$userListBody.on('click', '.groups .groupsListContainer, .subadmins .groupsListContainer', function (event) {
		event.stopPropagation();
		var $td = $(this).closest('td');
		var isSubadminSelect = $td.hasClass('subadmins');
		UserList._triggerGroupEdit($td, isSubadminSelect);
	});

	// init the quota field select box after it is shown the first time
	$('#app-settings').one('show', function() {
		$(this).find('#default_quota').singleSelect().on('change', UserList.onQuotaSelect);
	});

	UserList._updateGroupListLabel($('#newuser .groups'), []);
	$('#newuser').submit(function (event) {
		event.preventDefault();
		var userName = $('#newusername').val();
		var password = $('#newuserpassword').val();
		var email = $('#newemail').val();
		if ($.trim(userName) === '') {
			OC.Notification.showTemporary(t('user_management', 'Error creating user: {message}', {
				message: t('user_management', 'A valid user name must be provided')
			}));
			return false;
		}
		if ($.trim(password) === '') {
			OC.Notification.showTemporary(t('user_management', 'Error creating user: {message}', {
				message: t('user_management', 'A valid password must be provided')
			}));
			return false;
		}
		if(!$('#CheckboxMailOnUserCreate').is(':checked')) {
			email = '';
		}
		if ($('#CheckboxMailOnUserCreate').is(':checked') && $.trim(email) === '') {
			OC.Notification.showTemporary( t('user_management', 'Error creating user: {message}', {
				message: t('user_management', 'A valid email must be provided')
			}));
			return false;
		}

		var promise;
		if (UserDeleteHandler) {
			promise = UserDeleteHandler.deleteEntry();
		} else {
			promise = $.Deferred().resolve().promise();
		}

		promise.then(function() {
			var groups = $('#newuser .groups').data('groups') || [];
			$.post(
				OC.generateUrl('/apps/user_management/users'),
				{
					userName: userName,
					password: password,
					groups: groups,
					email: email
				},
				function (result) {
					if (result.groups) {
						for (var i in result.groups) {
							var gid = result.groups[i];
							if(UserList.availableGroups.indexOf(gid) === -1) {
								UserList.availableGroups.push(gid);
							}
							$li = GroupList.getGroupLI(gid);
							userCount = GroupList.getUserCount($li);
							GroupList.setUserCount($li, userCount + 1);
						}
					}
					if(!UserList.has(userName)) {
						UserList.add(result, true);
					}
					$('#newusername').focus();
					GroupList.incEveryoneCount();
				}).fail(function(result) {
					OC.Notification.showTemporary(t('user_management', 'Error creating user: {message}', {
						message: result.responseJSON.message
					}, undefined, {escape: false}));
				}).success(function(){
					$('#newuser').get(0).reset();
				});
		});
	});

	if ($('#CheckboxIsEnabled').is(':checked')) {
			$("#userlist .enabled").show();
	}
	// Option to display/hide the "Enabled" column
	$('#CheckboxIsEnabled').click(function() {
			if ($('#CheckboxIsEnabled').is(':checked')) {
					$("#userlist .enabled").show();
					OC.AppConfig.setValue('core', 'umgmt_show_is_enabled', 'true');
			} else {
					$("#userlist .enabled").hide();
					OC.AppConfig.setValue('core', 'umgmt_show_is_enabled', 'false');
			}
	});


	if ($('#CheckboxStorageLocation').is(':checked')) {
		$("#userlist .storageLocation").show();
	}
	// Option to display/hide the "Storage location" column
	$('#CheckboxStorageLocation').click(function() {
		if ($('#CheckboxStorageLocation').is(':checked')) {
			$("#userlist .storageLocation").show();
			OC.AppConfig.setValue('core', 'umgmt_show_storage_location', 'true');
		} else {
			$("#userlist .storageLocation").hide();
			OC.AppConfig.setValue('core', 'umgmt_show_storage_location', 'false');
		}
	});

	if ($('#CheckboxLastLogin').is(':checked')) {
		$("#userlist .lastLogin").show();
	}
	// Option to display/hide the "Last Login" column
	$('#CheckboxLastLogin').click(function() {
		if ($('#CheckboxLastLogin').is(':checked')) {
			$("#userlist .lastLogin").show();
			OC.AppConfig.setValue('core', 'umgmt_show_last_login', 'true');
		} else {
			$("#userlist .lastLogin").hide();
			OC.AppConfig.setValue('core', 'umgmt_show_last_login', 'false');
		}
	});

	if ($('#CheckboxEmailAddress').is(':checked')) {
		$("#userlist .mailAddress").show();
	}
	// Option to display/hide the "Mail Address" column
	$('#CheckboxEmailAddress').click(function() {
		if ($('#CheckboxEmailAddress').is(':checked')) {
			$("#userlist .mailAddress").show();
			OC.AppConfig.setValue('core', 'umgmt_show_email', 'true');
		} else {
			$("#userlist .mailAddress").hide();
			OC.AppConfig.setValue('core', 'umgmt_show_email', 'false');
		}
	});

	if ($('#CheckboxUserBackend').is(':checked')) {
		$("#userlist .userBackend").show();
	}
	// Option to display/hide the "User Backend" column
	$('#CheckboxUserBackend').click(function() {
		if ($('#CheckboxUserBackend').is(':checked')) {
			$("#userlist .userBackend").show();
			OC.AppConfig.setValue('core', 'umgmt_show_backend', 'true');
		} else {
			$("#userlist .userBackend").hide();
			OC.AppConfig.setValue('core', 'umgmt_show_backend', 'false');
		}
	});

	if ($('#CheckboxMailOnUserCreate').is(':checked')) {
		$("#newemail").show();
	}
	// Option to display/hide the "E-Mail" input field
	$('#CheckboxMailOnUserCreate').click(function() {
		if ($('#CheckboxMailOnUserCreate').is(':checked')) {
			$("#newemail").show();
			OC.AppConfig.setValue('core', 'umgmt_send_email', 'true');
		} else {
			$("#newemail").hide();
			OC.AppConfig.setValue('core', 'umgmt_send_email', 'false');
		}
	});

	// calculate initial limit of users to load
	var initialUserCountLimit = UserList.initialUsersToLoad,
		containerHeight = $('#app-content').height();
	if(containerHeight > 40) {
		initialUserCountLimit = Math.floor(containerHeight/40);
		if (initialUserCountLimit < UserList.initialUsersToLoad) {
			initialUserCountLimit = UserList.initialUsersToLoad;
		}
	}
	//realign initialUserCountLimit with usersToLoad as a safeguard
	while((initialUserCountLimit % UserList.usersToLoad) !== 0) {
		// must be a multiple of this, otherwise LDAP freaks out.
		// FIXME: solve this in LDAP backend in  8.1
		initialUserCountLimit = initialUserCountLimit + 1;
	}

	// trigger loading of users on startup
	UserList.update(UserList.currentGid, initialUserCountLimit);

	_.defer(function() {
		$('#app-content').trigger($.Event('apprendered'));
	});

});
