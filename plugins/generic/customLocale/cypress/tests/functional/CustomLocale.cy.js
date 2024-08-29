/**
 * @file cypress/tests/functional/CustomLocale.cy.js
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 */

describe('Custom Locale plugin tests', function() {
	it('Enables and configures the plugin', function() {
		cy.login('admin', 'admin', 'publicknowledge');

		cy.get('.app__nav a').contains('Website').click();
		cy.get('button[id="plugins-button"]').click();

		// Find and enable the plugin
		cy.get('input[id^="select-cell-customlocaleplugin-enabled"]').click();
		cy.waitJQuery();
		cy.get('div:contains(\'The plugin "Custom Locale Plugin" has been enabled.\')');
		cy.get('tr[id="component-grid-settings-plugins-settingsplugingrid-category-generic-row-customlocaleplugin"] a.show_extras').click();
		cy.get('a[id^="component-grid-settings-plugins-settingsplugingrid-category-generic-row-customlocaleplugin-customize-button-"').click();

		// FIXME: The new settings tab handler doesn't jump right to the correct tab.
		cy.get('button#customLocale-button').click();
		cy.get('#customLocale a[href$=en]:contains("Edit")').click();
		cy.wait(1000); // Form init
		cy.get('#localeFilesForm input.pkpSearch__input').type('user.affiliation');
		cy.get('#localeFilesForm button.pkpButton:contains("Search")').click();
		cy.get('#localeFilesForm table.customLocale__cellTable').should('have.length', 3)
		cy.get('#localeFilesForm td input[name="changes[user.affiliation]"]').type('Floog Bleem', {delay: 0});
		cy.get('#localeFilesForm button:contains("Save and continue")').click();
		cy.waitJQuery();
		cy.get('#localeFilesForm a:contains("Cancel")').click();

		// Check that the overridden locale key works.
		cy.get('.app__userNav button').click();
		cy.get('.app__userNav a:contains("Edit Profile")').click({ force: true }); // Force workaround for lack of .hover() in Cypress
		cy.wait(5000); // Delay to ensure cache refresh
		cy.get('a:contains("Contact")').click();
		cy.get('label[for^="affiliation-en"]:contains("Floog Bleem")');
	});
});
