import '../support/commands.js';

describe('Attach calendar invitation to reviewer emails', function () {
    let submissionTitle = 'Sodium butyrate improves growth performance of weaned piglets';

    it('Attaches the calendar invitation when adding a reviewer', function () {
        cy.login('admin', 'admin', 'publicknowledge');
        cy.findSubmission('active', submissionTitle);
        cy.get('[data-cy="reviewer-manager"]')
            .find('button:contains("Add Reviewer")')
            .click();
        cy.get('div[role="dialog"]:contains("Add Reviewer")')
            .find('div.listPanel__itemTitle:contains("Adela Gallego")')
            .parents('li.listPanel__item')
            .find('button:contains("Select Reviewer")')
            .click();
        cy.get('div[role="dialog"]:contains("Add Reviewer")').last().within(() => {
            cy.get('input[name="responseDueDate-removed"]').clear().type('2020-01-01').blur();
            cy.get('input[name="responseDueDate"]').should('have.value', '2020-01-01');
            cy.get('input[name="reviewDueDate-removed"]').clear().type('2020-01-01').blur();
            cy.get('input[name="reviewDueDate"]').should('have.value', '2020-01-01');
            cy.get('button:contains("Add Reviewer")').last().click();
        });

        cy.visit('localhost:8025');
        cy.contains('b', 'Invitation to review').click();
        cy.get('#nav-tab button:contains("Text")').click();
        cy.contains('invite.ics');
    });

    it('Attaches the calendar invitation when sending a reminder', function () {
        cy.login('admin', 'admin', 'publicknowledge');
        cy.findSubmission('active', submissionTitle);
        cy.get('[data-cy="reviewer-manager"]')
            .contains('tr', 'Adela Gallego')
            .within(() => {
                cy.get('button[aria-label="More Actions"]').click();
                cy.contains('button', 'Send Reminder').click();
            });
        cy.get('[id^="submitFormButton-"]').contains('Send Reminder').click();

        cy.visit('localhost:8025');
        cy.contains('b', 'A reminder to please complete your review').click();
        cy.get('#nav-tab button:contains("Text")').click();
        cy.contains('invite.ics');
    });
});
