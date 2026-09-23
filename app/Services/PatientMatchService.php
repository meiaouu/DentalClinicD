<?php

namespace App\Services;

use App\Repositories\PatientRepository;

class PatientMatchService
{
    private PatientRepository $patients;

    public function __construct()
    {
        $this->patients = new PatientRepository();
    }

    public function findExistingPatient(array $data): ?array
    {
        $contactNumber = trim((string) ($data['contact_number'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $firstName = trim((string) ($data['first_name'] ?? ''));
        $middleName = trim((string) ($data['middle_name'] ?? ''));
        $lastName = trim((string) ($data['last_name'] ?? ''));
        $birthDate = !empty($data['birth_date']) ? (string) $data['birth_date'] : null;

        if ($contactNumber !== '') {
            $match = $this->patients->findDuplicateByContact($contactNumber);
            if ($match) {
                return $match;
            }
        }

        if ($email !== '') {
            $match = $this->patients->findDuplicateByEmail($email);
            if ($match) {
                return $match;
            }
        }

        if ($firstName !== '' && $lastName !== '' && $birthDate) {
            $match = $this->patients->findDuplicateByNameAndBirthDate(
                $firstName,
                $middleName,
                $lastName,
                $birthDate
            );
            if ($match) {
                return $match;
            }
        }

        return null;
    }
}