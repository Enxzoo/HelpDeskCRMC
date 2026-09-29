<?php
/**
 * InquiryController.php
 * Handles HTTP-facing logic for student inquiries: receiving the request,
 * calling models, and returning a response.
 */

require_once __DIR__ . '/../models/Inquiry.php';

class InquiryController
{
    /**
     * $studentId comes from $_SESSION, never from $_POST — a student
     * should never be able to submit an inquiry as someone else by
     * editing form data.
     */
    public function submit(int $studentId, string $message, ?string $office = null, ?string $subject = null): array
    {
        $message = trim($message);

        if ($message === '') {
            return ['success' => false, 'error' => 'Message cannot be empty.'];
        }

        error_log("[InquiryController::submit] student_id: {$studentId} office: {$office} message: {$message}");

        $inquiryModel = new Inquiry();

        // Save the inquiry to the database (status defaults to Pending)
        $inquiryData = [
            'student_id' => $studentId,
            'message'    => $message,
            'office_id'  => $office,
        ];

        if (!empty($subject)) {
            $inquiryData['subject'] = $subject;
        }

        $inquiryId = $inquiryModel->create($inquiryData);

        return [
            'success'    => true,
            'inquiry_id' => $inquiryId,
        ];
    }

    public function listForStudent(int $studentId): array
    {
        $inquiryModel = new Inquiry();
        return $inquiryModel->findByStudent($studentId);
    }
}