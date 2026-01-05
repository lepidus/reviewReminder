import '../support/commands.js';

describe('Send Review Reminder', function () {
    let submissionTitle = 'Sodium butyrate improves growth performance of weaned piglets';
    
    it('Add Reviewer to Submission', function () {
        cy.login('admin', 'admin', 'publicknowledge');
        cy.findSubmission('active', submissionTitle);
        cy.contains('a', 'Add Reviewer').click();
        cy.contains('Adela Gallego').parent().parent().within(() => {
            cy.contains('Select Reviewer').click();
        });
        cy.contains('Do not send email to Reviewer').parent().within(() => {
            cy.get('input[type="checkbox"]').check();
        });
        cy.get('[id^="submitFormButton-"]').contains('Add Reviewer').click();
    })
    it('Email should not be sent yet', function () {
        cy.visit('localhost:8025');
        cy.get('b:contains("Review Reminder")').should('not.exist');
    })
    it('Reviewer accepts review assignment', function () {
        cy.login('agallego', null, 'publicknowledge');
        cy.findSubmission('myQueue', submissionTitle);

        cy.contains('Yes, I agree to have my data collected and stored').parent().within(() => {
            cy.get('input[type="checkbox"]').check();
        });
        cy.contains('button', 'Accept Review, Continue to Step #2').click();
    });
    it('Review reminder has been sent', function () {
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
    });
});