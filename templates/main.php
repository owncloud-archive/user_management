<?php
/**
 * Copyright (c) 2011, Robin Appelman <icewind1991@gmail.com>
 * This file is licensed under the Affero General Public License version 3 or later.
 * See the COPYING-README file.
 */

script('user_management', [
	'deleteHandler',
	'filter',
	'users',
	'groups'
]);
script('core', [
	'multiselect',
	'singleselect'
]);
style('user_management', 'style');

// necessary for translating the menu entry
$l->t("Users");

$userlistParams = [];
$allGroups= [];
foreach ($_["adminGroup"] as $group) {
	$allGroups[] = $group['name'];
}
foreach ($_["groups"] as $group) {
	$allGroups[] = $group['name'];
}
$userlistParams['subadmingroups'] = $allGroups;
$userlistParams['allGroups'] = \json_encode($allGroups);
$items = \array_flip($userlistParams['subadmingroups']);
unset($items['admin']);
$userlistParams['subadmingroups'] = \array_flip($items);

translation('settings');
?>

<div id="app-navigation">
	<?php print_unescaped($this->inc('part.grouplist')); ?>
	<div id="app-settings">
		<div id="app-settings-header">
			<button class="settings-button" tabindex="0" data-apps-slide-toggle="#app-settings-content"></button>
		</div>
		<div id="app-settings-content">
			<?php print_unescaped($this->inc('part.setquota')); ?>

			<div id="userlistoptions">
				<p>
					<input type="checkbox" name="IsEnabled" value="IsEnabled" id="CheckboxIsEnabled" 
						class="checkbox" <?php if ($_['show_is_enabled'] === 'true') {
	print_unescaped('checked="checked"');
} ?> />
					<label for="CheckboxIsEnabled">
						<?php p($l->t('Show enabled/disabled option')) ?>
					</label>
				</p>
				<p>
					<input type="checkbox" name="StorageLocation" value="StorageLocation" id="CheckboxStorageLocation" 
						class="checkbox" <?php if ($_['show_storage_location'] === 'true') {
	print_unescaped('checked="checked"');
} ?> />
					<label for="CheckboxStorageLocation">
						<?php p($l->t('Show storage location')) ?>
					</label>
				</p>
				<p>
					<input type="checkbox" name="LastLogin" value="LastLogin" id="CheckboxLastLogin"
						class="checkbox" <?php if ($_['show_last_login'] === 'true') {
	print_unescaped('checked="checked"');
} ?> />
					<label for="CheckboxLastLogin">
						<?php p($l->t('Show last log in')) ?>
					</label>
				</p>
				<p>
					<input type="checkbox" name="UserBackend" value="UserBackend" id="CheckboxUserBackend"
						class="checkbox" <?php if ($_['show_backend'] === 'true') {
	print_unescaped('checked="checked"');
} ?> />
					<label for="CheckboxUserBackend">
						<?php p($l->t('Show user backend')) ?>
					</label>
				</p>
				<p>
					<input type="checkbox" name="AddPasswordOnUserCreate" value="AddPasswordOnUserCreate" id="CheckBoxPasswordOnUserCreate"
						   class="checkbox" <?php if ($_['set_password'] === 'true') {
	print_unescaped('checked="checked"');
} ?> />
					<label for="CheckBoxPasswordOnUserCreate">
						<?php p($l->t('Set password for new users')) ?>
					</label>
				</p>
				<p>
					<input type="checkbox" name="EmailAddress" value="EmailAddress" id="CheckboxEmailAddress"
						class="checkbox" <?php if ($_['show_email'] === 'true') {
	print_unescaped('checked="checked"');
} ?> />
					<label for="CheckboxEmailAddress">
						<?php p($l->t('Show email address')) ?>
					</label>
				</p>
			</div>
		</div>
	</div>
</div>

<div id="app-content">
	<?php print_unescaped($this->inc('part.createuser')); ?>
	<?php print_unescaped($this->inc('part.userlist', $userlistParams)); ?>
</div>
