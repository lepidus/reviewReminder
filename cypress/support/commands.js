Cypress.Commands.add('findSubmission', function(tab, title) {
	const viewNames = {
		active: 'Active submissions',
		archive: 'Archived submissions',
		myQueue: 'My queue',
	};

	cy.get('nav').contains(viewNames[tab]).click();
	cy.contains('table tr', title).within(() => {
		cy.contains('button', /^\s*View\s*$/)
			.scrollIntoView()
			.should('be.visible')
			.click({force: true});
	});
});
