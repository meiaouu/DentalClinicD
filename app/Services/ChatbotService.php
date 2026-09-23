<?php

namespace App\Services;

class ChatbotService
{
    private string $clinicName = 'Dental Clinic';
    private string $clinicContact = 'the clinic';

    public function suggestReply(array $conversation, array $messages = []): string
    {
        $lastIncomingMessage = $this->lastIncomingMessage($messages);
        $patientName = $this->conversationName($conversation);

        if ($lastIncomingMessage === '') {
            return 'Hello ' . $patientName . ', this is ' . $this->clinicName . '. How may we help you today?';
        }

        return $this->replyForMessage($lastIncomingMessage, $patientName);
    }

    public function quickReplies(array $conversation = []): array
    {
        $patientName = $this->conversationName($conversation);

        return [
            'Hello ' . $patientName . ', this is ' . $this->clinicName . '. How may we help you today?',
            'Thank you for your message. Our staff will review this and assist you shortly.',
            'For appointment concerns, please provide your preferred date, time, service, and contact number.',
            'For urgent dental concerns, please contact the clinic directly so staff can assist you faster.',
            'Please avoid sending sensitive medical details here. Our dentist can discuss clinical concerns during your visit.',
        ];
    }

    private function replyForMessage(string $message, string $patientName): string
    {
        $text = strtolower($message);

        if ($this->containsAny($text, ['appointment', 'schedule', 'book', 'booking', 'available', 'slot'])) {
            return 'Hello ' . $patientName . ', thank you for contacting ' . $this->clinicName . '. For appointment booking, please send your preferred date, preferred time, dental service, and contact number so our staff can assist you.';
        }

        if ($this->containsAny($text, ['reschedule', 'change date', 'change time', 'move appointment'])) {
            return 'Hello ' . $patientName . ', we can help you with rescheduling. Please send your current appointment date and your preferred new date and time. Our staff will confirm if the slot is available.';
        }

        if ($this->containsAny($text, ['cancel', 'cancelled', 'cancellation'])) {
            return 'Hello ' . $patientName . ', we can assist with cancellation. Please confirm the appointment date and patient name so our staff can review your request.';
        }

        if ($this->containsAny($text, ['price', 'cost', 'fee', 'how much', 'payment', 'billing'])) {
            return 'Hello ' . $patientName . ', service fees may depend on the procedure needed. Our staff can provide an estimated fee, but the final amount may be confirmed after dental assessment.';
        }

        if ($this->containsAny($text, ['pain', 'toothache', 'swelling', 'bleeding', 'emergency'])) {
            return 'Hello ' . $patientName . ', we are sorry to hear that. For urgent dental concerns such as severe pain, swelling, or bleeding, please contact the clinic directly or visit the nearest emergency facility if needed.';
        }

        if ($this->containsAny($text, ['open', 'hours', 'time', 'clinic hour', 'schedule today'])) {
            return 'Hello ' . $patientName . ', please wait while our staff checks the clinic schedule and available appointment slots for you.';
        }

        if ($this->containsAny($text, ['record', 'document', 'xray', 'x-ray', 'attachment', 'upload'])) {
            return 'Hello ' . $patientName . ', you may send or upload the needed document if available. Our staff or dentist will review it as part of your clinic record.';
        }

        if ($this->containsAny($text, ['thank', 'thanks', 'okay', 'ok'])) {
            return 'You are welcome, ' . $patientName . '. Please let us know if you need further assistance.';
        }

        return 'Hello ' . $patientName . ', thank you for your message. Our staff will review your concern and assist you shortly.';
    }

    private function lastIncomingMessage(array $messages): string
    {
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            $message = $messages[$i];

            if (!is_array($message)) {
                continue;
            }

            $senderType = strtolower(trim((string) ($message['sender_type'] ?? '')));

            if (in_array($senderType, ['staff', 'admin', 'clinic', 'bot'], true)) {
                continue;
            }

            $text = trim((string) (
                $message['message_text']
                ?? $message['message_body']
                ?? $message['body']
                ?? $message['content']
                ?? ''
            ));

            if ($text !== '') {
                return $this->limitText($text, 1000);
            }
        }

        return '';
    }

    private function conversationName(array $conversation): string
    {
        $name = trim(
            (string) (($conversation['patient_first_name'] ?? '') . ' ' .
            ($conversation['patient_middle_name'] ?? '') . ' ' .
            ($conversation['patient_last_name'] ?? ''))
        );

        if ($name !== '') {
            return $name;
        }

        $guestName = trim(
            (string) (($conversation['guest_first_name'] ?? '') . ' ' .
            ($conversation['guest_middle_name'] ?? '') . ' ' .
            ($conversation['guest_last_name'] ?? ''))
        );

        return $guestName !== '' ? $guestName : 'there';
    }

    private function containsAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function limitText(string $text, int $maxLength): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($text, 0, $maxLength);
        }

        return substr($text, 0, $maxLength);
    }
}