describe('Setup Review Reminder plugin', function () {
    it('Enable the plugin in the plugins list', function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.contains('a', 'Website').click();
        cy.waitJQuery();
        cy.get('#plugins-button').click();
        cy.get('input[id^=select-cell-reviewreminderplugin]').check();
        cy.get('input[id^=select-cell-reviewreminderplugin]').should('be.checked');
    })
})

