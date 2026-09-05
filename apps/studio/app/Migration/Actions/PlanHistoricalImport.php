<?php

namespace App\Migration\Actions;

use App\Contracts\Enums\ContractStatus;
use App\Crm\Enums\BookedVia;
use App\Crm\Models\Contact;
use App\Crm\Support\CrmVocabulary;
use App\Migration\Support\ImportPlan;
use DateTime;

/**
 * Reads the four CSV files a historical import directory holds — see
 * docs/historical-import.md for the column reference — and resolves every
 * row into either something CommitHistoricalImport can write, or an error
 * naming exactly why the row can't be mapped. Never writes anything itself:
 * the one DB read it does (matching a contact by email, so re-running an
 * import never duplicates a contact CommitHistoricalImport already created)
 * is the same lookup a commit would do anyway.
 *
 * `ref` columns are a legacy identifier that exists only to link rows to
 * each other within one import run — contacts.csv's `ref` is what
 * projects.csv's `primary_contact_ref` points at. Nothing derived from it is
 * ever stored on the model it resolves to.
 */
class PlanHistoricalImport
{
    public function __invoke(string $path): ImportPlan
    {
        [$contactsToCreate, $contactsToReuse, $contactErrors, $contactRefs] = $this->planContacts("{$path}/contacts.csv");
        [$projectsToCreate, $projectErrors, $projectRefs] = $this->planProjects("{$path}/projects.csv", $contactRefs);
        [$jobsToCreate, $jobErrors] = $this->planJobs("{$path}/jobs.csv", $projectRefs);
        [$contractsToCreate, $contractErrors] = $this->planContracts("{$path}/contracts.csv", $projectRefs);

        return new ImportPlan(
            contacts: ['toCreate' => $contactsToCreate, 'toReuse' => $contactsToReuse, 'errors' => $contactErrors],
            projects: ['toCreate' => $projectsToCreate, 'errors' => $projectErrors],
            jobs: ['toCreate' => $jobsToCreate, 'errors' => $jobErrors],
            contracts: ['toCreate' => $contractsToCreate, 'errors' => $contractErrors],
        );
    }

    /**
     * @return array{0: list<array>, 1: list<array>, 2: list<array>, 3: array<string,true>}
     */
    private function planContacts(string $file): array
    {
        $toCreate = [];
        $toReuse = [];
        $errors = [];
        $seenRefs = [];

        foreach ($this->readCsv($file) as $i => $row) {
            $rowNumber = $i + 1;
            $ref = $this->cell($row, 'ref');
            $name = $this->cell($row, 'name');
            $email = $this->cell($row, 'email');
            $phone = $this->cell($row, 'phone');

            if ($ref === null) {
                $errors[] = ['row' => $rowNumber, 'ref' => null, 'reason' => 'ref is required'];

                continue;
            }

            if (isset($seenRefs[$ref])) {
                $errors[] = ['row' => $rowNumber, 'ref' => $ref, 'reason' => "duplicate ref '{$ref}' in contacts.csv"];

                continue;
            }

            if ($name === null) {
                $errors[] = ['row' => $rowNumber, 'ref' => $ref, 'reason' => 'name is required'];

                continue;
            }

            $seenRefs[$ref] = true;

            $existing = $email !== null ? Contact::query()->where('email', $email)->first() : null;

            if ($existing !== null) {
                $toReuse[] = ['ref' => $ref, 'contact' => $existing];
            } else {
                $toCreate[] = ['ref' => $ref, 'name' => $name, 'email' => $email, 'phone' => $phone];
            }
        }

        return [$toCreate, $toReuse, $errors, $seenRefs];
    }

    /**
     * @param  array<string,true>  $contactRefs
     * @return array{0: list<array>, 1: list<array>, 2: array<string,true>}
     */
    private function planProjects(string $file, array $contactRefs): array
    {
        $toCreate = [];
        $errors = [];
        $seenRefs = [];

        foreach ($this->readCsv($file) as $i => $row) {
            $rowNumber = $i + 1;
            $ref = $this->cell($row, 'ref');
            $name = $this->cell($row, 'name');
            $typeRaw = $this->cell($row, 'type');
            $contactRef = $this->cell($row, 'primary_contact_ref');

            if ($ref === null) {
                $errors[] = ['row' => $rowNumber, 'ref' => null, 'reason' => 'ref is required'];

                continue;
            }

            if (isset($seenRefs[$ref])) {
                $errors[] = ['row' => $rowNumber, 'ref' => $ref, 'reason' => "duplicate ref '{$ref}' in projects.csv"];

                continue;
            }

            if ($name === null) {
                $errors[] = ['row' => $rowNumber, 'ref' => $ref, 'reason' => 'name is required'];

                continue;
            }

            $type = $typeRaw !== null ? app(CrmVocabulary::class)->type(strtolower($typeRaw)) : null;

            if ($type === null) {
                $values = app(CrmVocabulary::class)->types()->pluck('key')->implode(', ');
                $errors[] = ['row' => $rowNumber, 'ref' => $ref, 'reason' => "type must be one of: {$values}"];

                continue;
            }

            if ($contactRef === null) {
                $errors[] = ['row' => $rowNumber, 'ref' => $ref, 'reason' => 'primary_contact_ref is required'];

                continue;
            }

            if (! isset($contactRefs[$contactRef])) {
                $errors[] = ['row' => $rowNumber, 'ref' => $ref, 'reason' => "primary_contact_ref '{$contactRef}' not found in contacts.csv"];

                continue;
            }

            $seenRefs[$ref] = true;
            $toCreate[] = ['ref' => $ref, 'name' => $name, 'type' => $type, 'primary_contact_ref' => $contactRef];
        }

        return [$toCreate, $errors, $seenRefs];
    }

    /**
     * @param  array<string,true>  $projectRefs
     * @return array{0: list<array>, 1: list<array>}
     */
    private function planJobs(string $file, array $projectRefs): array
    {
        $toCreate = [];
        $errors = [];
        $seenProjectRefs = [];

        foreach ($this->readCsv($file) as $i => $row) {
            $rowNumber = $i + 1;
            $projectRef = $this->cell($row, 'project_ref');
            $bookedAtRaw = $this->cell($row, 'booked_at');
            $bookedViaRaw = $this->cell($row, 'booked_via') ?? BookedVia::Manual->value;

            if ($projectRef === null) {
                $errors[] = ['row' => $rowNumber, 'ref' => null, 'reason' => 'project_ref is required'];

                continue;
            }

            if (! isset($projectRefs[$projectRef])) {
                $errors[] = ['row' => $rowNumber, 'ref' => $projectRef, 'reason' => "project_ref '{$projectRef}' not found in projects.csv"];

                continue;
            }

            // Job.project_id is a unique FK — one job per project, per
            // App\Crm\Actions\BookProject. A second row for the same project
            // would fail this same way at commit time; caught here so the
            // dry run reports it too.
            if (isset($seenProjectRefs[$projectRef])) {
                $errors[] = ['row' => $rowNumber, 'ref' => $projectRef, 'reason' => "project '{$projectRef}' already has a job in this import"];

                continue;
            }

            if ($bookedAtRaw === null) {
                $errors[] = ['row' => $rowNumber, 'ref' => $projectRef, 'reason' => 'booked_at is required'];

                continue;
            }

            $bookedAt = $this->parseDate($bookedAtRaw);

            if ($bookedAt === null) {
                $errors[] = ['row' => $rowNumber, 'ref' => $projectRef, 'reason' => 'booked_at must be a valid date (YYYY-MM-DD)'];

                continue;
            }

            $bookedVia = BookedVia::tryFrom(strtolower($bookedViaRaw));

            if ($bookedVia === null) {
                $errors[] = ['row' => $rowNumber, 'ref' => $projectRef, 'reason' => 'booked_via must be one of: manual, contract'];

                continue;
            }

            $seenProjectRefs[$projectRef] = true;
            $toCreate[] = ['project_ref' => $projectRef, 'booked_at' => $bookedAt, 'booked_via' => $bookedVia];
        }

        return [$toCreate, $errors];
    }

    /**
     * @param  array<string,true>  $projectRefs
     * @return array{0: list<array>, 1: list<array>}
     */
    private function planContracts(string $file, array $projectRefs): array
    {
        $toCreate = [];
        $errors = [];

        foreach ($this->readCsv($file) as $i => $row) {
            $rowNumber = $i + 1;
            $projectRef = $this->cell($row, 'project_ref');
            $statusRaw = $this->cell($row, 'status') ?? ContractStatus::Rendered->value;
            $renderedAtRaw = $this->cell($row, 'rendered_at');
            $artifactPath = $this->cell($row, 'artifact_path');

            if ($projectRef === null) {
                $errors[] = ['row' => $rowNumber, 'ref' => null, 'reason' => 'project_ref is required'];

                continue;
            }

            if (! isset($projectRefs[$projectRef])) {
                $errors[] = ['row' => $rowNumber, 'ref' => $projectRef, 'reason' => "project_ref '{$projectRef}' not found in projects.csv"];

                continue;
            }

            $status = ContractStatus::tryFrom(strtolower($statusRaw));

            if ($status === null) {
                $values = implode(', ', array_column(ContractStatus::cases(), 'value'));
                $errors[] = ['row' => $rowNumber, 'ref' => $projectRef, 'reason' => "status must be one of: {$values}"];

                continue;
            }

            $renderedAt = null;

            if ($status === ContractStatus::Rendered) {
                if ($renderedAtRaw === null) {
                    $errors[] = ['row' => $rowNumber, 'ref' => $projectRef, 'reason' => 'rendered_at is required when status is rendered'];

                    continue;
                }

                $renderedAt = $this->parseDate($renderedAtRaw);

                if ($renderedAt === null) {
                    $errors[] = ['row' => $rowNumber, 'ref' => $projectRef, 'reason' => 'rendered_at must be a valid date (YYYY-MM-DD)'];

                    continue;
                }
            } elseif ($renderedAtRaw !== null) {
                $renderedAt = $this->parseDate($renderedAtRaw);
            }

            $toCreate[] = ['project_ref' => $projectRef, 'status' => $status, 'rendered_at' => $renderedAt, 'artifact_path' => $artifactPath];
        }

        return [$toCreate, $errors];
    }

    /**
     * Every row as an assoc array keyed by the header row. Missing file
     * (an entity the studio has nothing historical for) is not an error —
     * it simply contributes zero rows.
     *
     * @return list<array<string,string>>
     */
    private function readCsv(string $file): array
    {
        if (! is_file($file)) {
            return [];
        }

        $handle = fopen($file, 'r');
        $header = fgetcsv($handle);
        $rows = [];

        while (($data = fgetcsv($handle)) !== false) {
            if ($data === [null]) {
                continue; // a blank trailing line
            }

            $rows[] = array_combine($header, array_pad($data, count($header), null));
        }

        fclose($handle);

        return $rows;
    }

    /** Trims a cell and folds '' to null, so every required-field check is one comparison. */
    private function cell(array $row, string $key): ?string
    {
        $value = trim((string) ($row[$key] ?? ''));

        return $value === '' ? null : $value;
    }

    private function parseDate(string $value): ?string
    {
        $date = DateTime::createFromFormat('!Y-m-d', $value);

        if ($date === false || $date->format('Y-m-d') !== $value) {
            return null;
        }

        return $value;
    }
}
