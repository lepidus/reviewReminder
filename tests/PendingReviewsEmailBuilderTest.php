<?php

use PHPUnit\Framework\TestCase;
use APP\core\Application;
use APP\journal\Journal;
use PKP\user\User;
use APP\submission\Submission;
use APP\publication\Publication;
use APP\plugins\generic\reviewReminder\classes\PendingReviewsEmailBuilder;

class PendingReviewsEmailBuilderTest extends TestCase
{
    private $locale = 'en';
    private $context;
    private $reviewer;
    private $submissions;
    private $pendingReviewsEmailBuilder;

    public function setUp(): void
    {
        $this->context = $this->createTestContext();
        $this->reviewer = $this->createReviewerUser();
        $this->submissions = $this->createTestSubmissions();
        $this->pendingReviewsEmailBuilder = new PendingReviewsEmailBuilder(
            $this->context,
            $this->reviewer,
            $this->submissions,
            $this->locale
        );
    }

    private function createTestContext()
    {
        $context = new Journal();
        $context->setData('name', 'Example Journal', $this->locale);
        $context->setData('contactName', 'Example contact');
        $context->setData('contactEmail', 'example.contact@gmail.com');
        $context->setData('urlPath', 'example-journal');

        return $context;
    }

    private function createReviewerUser(): User
    {
        $reviewer = new User();
        $reviewer->setData('id', 2024);
        $reviewer->setData('email', 'juancarlo.rodriguez@gmail.com');
        $reviewer->setData('givenName', 'Juan Carlo', $this->locale);
        $reviewer->setData('familyName', 'Rodriguez', $this->locale);

        return $reviewer;
    }

    private function createTestSubmissions(): array
    {
        $fiveDaysLater = new DateTime();
        $sixDaysLater = new DateTime();
        $fiveDaysLater = $fiveDaysLater->modify('+5 days')->format('Y-m-d H:i:s');
        $sixDaysLater = $sixDaysLater->modify('+6 days')->format('Y-m-d H:i:s');

        $firstPublication = new Publication();
        $firstPublication->setAllData([
            'id' => 147,
            'title' => [$this->locale => 'First Submission Title'],
        ]);
        $firstSubmission = new Submission();
        $firstSubmission->setAllData([
            'id' => 123,
            'currentPublicationId' => $firstPublication->getId(),
            'publications' => [$firstPublication],
        ]);

        $secondPublication = new Publication();
        $secondPublication->setAllData([
            'id' => 148,
            'title' => [$this->locale => 'Second Submission Title'],
        ]);
        $secondSubmission = new Submission();
        $secondSubmission->setAllData([
            'id' => 124,
            'currentPublicationId' => $secondPublication->getId(),
            'publications' => [$secondPublication],
        ]);

        return [
            ['submission' => $firstSubmission, 'reviewDueDate' => $fiveDaysLater],
            ['submission' => $secondSubmission, 'reviewDueDate' => $sixDaysLater],
        ];
    }

    private function getSubmissionsString(): string
    {
        $request = Application::get()->getRequest();
        $dispatcher = Application::get()->getDispatcher();
        $request->setDispatcher($dispatcher);

        $submissionsString = '';
        foreach ($this->submissions as $submissionData) {
            $submission = $submissionData['submission'];
            $url = $request->getDispatcher()->url(
                $request,
                Application::ROUTE_PAGE,
                $this->context->getData('urlPath'),
                'reviewer',
                'submission',
                null,
                [$submission->getId()]
            );

            $submissionString = "<p><a href=\"$url\">" . $submission->getData('title', $this->locale) . '</a> - '
                . __('plugins.generic.reviewReminder.reviewDueDate', $this->locale, $submissionData['reviewDueDate']) . '</p>';

            $submissionsString .= $submissionString;
        }

        return $submissionsString;
    }

    public function testPendingReviewsEmailBuilding(): void
    {
        $email = $this->pendingReviewsEmailBuilder->buildEmail();

        $expectedFrom = ['name' => $this->context->getContactName(), 'address' => $this->context->getContactEmail()];
        $this->assertEquals($expectedFrom, $email->from[0]);

        $expectedTo = [['name' => $this->reviewer->getFullName(), 'address' => $this->reviewer->getEmail()]];
        $this->assertEquals($expectedTo, $email->to);

        $expectedSubject = __('emails.pendingReviewsReminder.subject');
        $this->assertEquals($expectedSubject, $email->subject);

        $bodyParams = [
            'reviewerName' => $this->reviewer->getFullName(),
            'submissionsList' => $this->getSubmissionsString()
        ];
        $expectedBody = __('emails.pendingReviewsReminder.body', $bodyParams);
        $this->assertEquals($expectedBody, $email->view);
    }
}
