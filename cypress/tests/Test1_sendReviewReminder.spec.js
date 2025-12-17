describe('Send Review Reminder', function () {
    it('Add Reviewer to Submission', function () {
        cy.login('admin', 'admin', 'publicknowledge');
        cy.get('#archive-button').click();
        cy.get('#archive > .submissionsListPanel > .listPanel > .listPanel__body > .listPanel__items > .listPanel__itemsList > :nth-child(2) > .listPanel__item--submission > .listPanel__itemSummary > .listPanel__itemActions > .pkpButton').click();
        cy.get('#ui-id-3').click();
        cy.get('[id^="component-grid-users-reviewer-reviewergrid-addReviewer-button-"]').click();
        cy.get(':nth-child(4) > .listPanel__item--reviewer > .listPanel__itemSummary > .listPanel__itemActions > .pkpButton > [aria-hidden="true"]').click();
        cy.get('#skipEmail').click();
        cy.get('[id^="submitFormButton-"]').contains('Add Reviewer').click();
    })
    it('Check Email', function () {
        cy.visit('localhost:8025');

        cy.contains('b', 'Ramiro Vaca');
        cy.get('b:contains("Review Reminder")').should('have.length', 1);
        cy.contains('b', 'Review Reminder')
            .parent().parent().parent()
            .within((node) => {
                cy.contains('agallego@mailinator.com');
            });

        cy.get('b:contains("Review Reminder")').click();
        cy.get('#nav-tab button:contains("Text")').click();

        cy.contains('You can also use the attached reminder to add this event to your preferred calendar');
        cy.contains('invite.ics');
    })
});