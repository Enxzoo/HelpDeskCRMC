<?php
/**
 * InquiryController.php
 * Handles HTTP-facing logic for student inquiries: receiving the request,
 * calling models, and returning a response.
 */

require_once __DIR__ . '/../models/Inquiry.php';
require_once __DIR__ . '/ConcernUrgencyClassifier.php';
require_once __DIR__ . '/ConcernDuplicateDetector.php';

class InquiryController
{
    private ConcernUrgencyClassifier $urgencyClassifier;
    private ConcernDuplicateDetector $duplicateDetector;

    public function __construct(
        ?ConcernUrgencyClassifier $urgencyClassifier = null,
        ?ConcernDuplicateDetector $duplicateDetector = null
    ) {
        $this->urgencyClassifier = $urgencyClassifier ?? new ConcernUrgencyClassifier();
        $this->duplicateDetector = $duplicateDetector ?? new ConcernDuplicateDetector();
    }

    /**
     * $studentId comes from $_SESSION, never from $_POST — a student
     * should never be able to submit an inquiry as someone else by
     * editing form data.
     */
    public function submit(
        int $studentId,
        string $message,
        ?int $office = null,
        ?string $subject = null,
        ?int $confirmedDuplicateOf = null
    ): array {
        $message = trim($message);

        if ($message === '') {
            return ['success' => false, 'error' => 'Message cannot be empty.'];
        }

        $subject = trim((string)$subject);
        if ($subject === '') {
            $subject = mb_substr($message, 0, 150);
        }

        $inquiryModel = new Inquiry();
        $duplicateRootId = null;
        $duplicateConfidence = null;
        $matchedConcernForError = null;

        if ($office !== null) {
            $candidates = $inquiryModel->findRecentDuplicateCandidates($studentId, $office);
            $match = $this->duplicateDetector->findMatch($subject, $message, $candidates);

            if ($match !== null) {
                $matchedCandidate = null;
                foreach ($candidates as $candidate) {
                    if ((int)$candidate['inquiry_id'] === (int)$match['inquiry_id']) {
                        $matchedCandidate = $candidate;
                        break;
                    }
                }

                if ($matchedCandidate !== null) {
                    $candidateRootId = empty($matchedCandidate['duplicate_of_inquiry_id'])
                        ? (int)$matchedCandidate['inquiry_id']
                        : (int)$matchedCandidate['duplicate_of_inquiry_id'];
                    $matchedConcern = [
                        'inquiry_id' => (int)$matchedCandidate['inquiry_id'],
                        'subject' => (string)$matchedCandidate['subject'],
                        'status' => (string)$matchedCandidate['status'],
                        'created_at' => (string)$matchedCandidate['created_at'],
                    ];
                    $matchedConcernForError = $matchedConcern;

                    if (
                        $matchedCandidate['status'] !== 'Resolved'
                        && $inquiryModel->isActiveDuplicateRoot($studentId, $office, $candidateRootId)
                    ) {
                        $duplicateCount = $inquiryModel->countRecentDuplicateSubmissions(
                            $studentId,
                            $office,
                            $candidateRootId
                        );

                        if ($duplicateCount >= Inquiry::DUPLICATE_SUBMISSION_LIMIT) {
                            return [
                                'success' => false,
                                'duplicate_limit_reached' => true,
                                'error' => 'A similar concern has already been submitted three times in the last 30 days. Please follow its status and staff replies instead of submitting it again.',
                                'matched_concern' => $matchedConcern,
                            ];
                        }

                        if ($confirmedDuplicateOf !== $candidateRootId) {
                            return [
                                'success' => false,
                                'duplicate_warning' => true,
                                'error' => 'A similar unresolved concern may already be open.',
                                'matched_concern' => $matchedConcern,
                                'duplicate_root_id' => $candidateRootId,
                                'submissions_in_window' => $duplicateCount,
                                'submission_limit' => Inquiry::DUPLICATE_SUBMISSION_LIMIT,
                            ];
                        }

                        $duplicateRootId = $candidateRootId;
                        $duplicateConfidence = (float)$match['confidence'];
                    }
                }
            }
        }

        $urgency = $this->urgencyClassifier->classify($subject, $message);
        $allowedPriorities = ['Critical/Urgent', 'High', 'Normal', 'Low'];
        $urgencyPriority = $urgency['priority'] ?? null;
        if (!in_array($urgencyPriority, $allowedPriorities, true)) {
            error_log('Urgency classifier returned an unsupported priority; using Normal pending staff review.');
            $urgencyPriority = 'Normal';
            $urgency['reason'] = 'No valid automated urgency result was available. Normal priority was assigned provisionally; staff review is required.';
            $urgency['confidence'] = null;
            $urgency['source'] = 'rule_fallback';
        }

        $inquiryData = [
            'student_id' => $studentId,
            'message'    => $message,
            'office_id'  => $office,
            'subject' => $subject,
            'ai_priority' => $urgencyPriority,
            'ai_priority_reason' => $urgency['reason'] ?? 'Automated urgency review is unavailable. Please assess this concern during triage.',
            'ai_priority_confidence' => $urgency['confidence'] ?? null,
        ];

        try {
            $inquiryId = $inquiryModel->create(
                $inquiryData,
                $duplicateRootId,
                $duplicateConfidence
            );
        } catch (DuplicateSubmissionLimitException $exception) {
            return [
                'success' => false,
                'duplicate_limit_reached' => true,
                'error' => 'A similar concern has already been submitted three times in the last 30 days. Please follow its status and staff replies instead of submitting it again.',
                'matched_concern' => $matchedConcernForError,
            ];
        }

        return [
            'success'    => true,
            'inquiry_id' => $inquiryId,
            'urgency_priority' => $urgencyPriority,
            'urgency_review_required' => ($urgency['source'] ?? 'rule_fallback') !== 'ai',
            'urgency_source' => $urgency['source'] ?? 'rule_fallback',
            'duplicate_of_inquiry_id' => $duplicateRootId,
        ];
    }

    public function listForStudent(int $studentId): array
    {
        $inquiryModel = new Inquiry();
        return $inquiryModel->findByStudent($studentId);
    }
}