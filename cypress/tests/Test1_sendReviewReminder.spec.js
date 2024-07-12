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
        cy.contains('Ramiro Vaca');
        cy.contains('agallego@mailinator.com');
        cy.contains('Review Reminder');
        cy.contains('You can use the attached reminder to add to the calendar of your choice.');
        cy.get('.subject > b').contains('Review Reminder').click();
        cy.contains('invite.ics');
    })
});