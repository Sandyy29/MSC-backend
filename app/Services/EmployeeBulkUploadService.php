<?php

namespace App\Services;

use App\Models\User;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Spatie\SimpleExcel\SimpleExcelReader;

class EmployeeBulkUploadService
{
    public function validateFile($filePath, $fileExtension)
    {
        $reader = SimpleExcelReader::create($filePath, $fileExtension)
            ->trimHeaderRow()
            ->getHeaders();

        // Recreate reader to parse rows
        $reader = SimpleExcelReader::create($filePath, $fileExtension)->trimHeaderRow();

        $results = [
            'total_rows' => 0,
            'valid_rows' => 0,
            'failed_rows' => 0,
            'errors' => [],
        ];

        $usernamesInFile = [];
        $emailsInFile = [];

        $rowIndex = 2; // Assuming row 1 is header

        // Check if file is empty
        try {
            $rows = $reader->getRows();
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Invalid file structure',
                'data' => $results,
            ];
        }

        foreach ($rows as $rawRow) {
            // Check if row is empty
            if (empty(array_filter($rawRow))) {
                $rowIndex++;

                continue;
            }

            // Normalize row keys (remove BOM, lowercase, spaces to underscores, trim)
            $row = [];
            foreach ($rawRow as $key => $value) {
                $cleanKey = preg_replace('/[\xef\xbb\xbf]+/', '', (string) $key);
                $cleanKey = strtolower(str_replace(' ', '_', trim($cleanKey)));
                $row[$cleanKey] = $value;
            }

            $results['total_rows']++;

            // Prepare data for validation
            $roleInput = null;
            if (! empty($row['role'])) {
                $roleInput = trim($row['role']);
                if (strtolower($roleInput) === 'qa / tester' || strtolower($roleInput) === 'qa/tester') {
                    $roleInput = 'QA / Tester';
                } else {
                    $roleInput = ucwords(strtolower($roleInput));
                }
                $row['role'] = $roleInput;
            }

            $branchInput = null;
            if (! empty($row['branch'])) {
                $branchInput = trim($row['branch']);
                $branchInput = ucwords(strtolower($branchInput));
                $row['branch'] = $branchInput;
            }

            // Basic validation
            $validator = Validator::make($row, [
                'full_name' => 'required|string|max:255',
                'username' => 'required|string|unique:users,username',
                'email' => 'required|email|unique:users,email',
                'phone' => 'nullable|string',
                'role' => 'required|string|in:Software Developer,Telecaller,Digital Marketing,QA / Tester,Other',
                'branch' => 'required|string|in:Dindigul,Madurai,Chennai,Coimbatore,Other',
                'password' => 'required|string|min:6',
                'confirm_password' => 'required|string|same:password',
            ], [
                'role.in' => 'Unsupported role: '.($row['role'] ?? 'empty'),
                'branch.in' => 'Unsupported branch: '.($row['branch'] ?? 'empty'),
                'confirm_password.same' => 'Passwords do not match.',
            ]);

            $rowErrors = [];

            if ($validator->fails()) {
                foreach ($validator->errors()->messages() as $field => $messages) {
                    $rowErrors[] = [
                        'row' => $rowIndex,
                        'field' => $field,
                        'message' => $messages[0],
                    ];
                }
            }

            // Check duplicates within the file
            if (! empty($row['username'])) {
                if (in_array($row['username'], $usernamesInFile)) {
                    $rowErrors[] = [
                        'row' => $rowIndex,
                        'field' => 'username',
                        'message' => 'Duplicate username in file',
                    ];
                }
                $usernamesInFile[] = $row['username'];
            }

            if (! empty($row['email'])) {
                if (in_array($row['email'], $emailsInFile)) {
                    $rowErrors[] = [
                        'row' => $rowIndex,
                        'field' => 'email',
                        'message' => 'Duplicate email in file',
                    ];
                }
                $emailsInFile[] = $row['email'];
            }

            if (! empty($rowErrors)) {
                $results['failed_rows']++;
                $messages = array_map(fn($err) => $err['message'], $rowErrors);
                $results['errors'][] = [
                    'row' => $rowIndex,
                    'errors' => $messages,
                ];
            } else {
                $results['valid_rows']++;
            }

            $rowIndex++;
        }

        return [
            'success' => $results['failed_rows'] === 0,
            'message' => $results['failed_rows'] > 0 ? 'Validation failed' : 'Validation successful',
            'data' => $results,
        ];
    }

    public function processFile($filePath, $fileExtension, $managerId)
    {
        $validationResults = $this->validateFile($filePath, $fileExtension);

        if (! $validationResults['success']) {
            return $validationResults;
        }

        $reader = SimpleExcelReader::create($filePath, $fileExtension)->trimHeaderRow();

        $rows = $reader->getRows();
        $createdCount = 0;

        DB::beginTransaction();

        $rowIndex = 2; // Assuming row 1 is header

        try {
            foreach ($rows as $rawRow) {
                // Normalize row keys
                $row = [];
                foreach ($rawRow as $key => $value) {
                    $cleanKey = preg_replace('/[\xef\xbb\xbf]+/', '', (string) $key);
                    $cleanKey = strtolower(str_replace(' ', '_', trim($cleanKey)));
                    $row[$cleanKey] = $value;
                }

                $roleInput = null;
                if (! empty($row['role'])) {
                    $roleInput = trim($row['role']);
                    if (strtolower($roleInput) === 'qa / tester' || strtolower($roleInput) === 'qa/tester') {
                        $roleInput = 'QA / Tester';
                    } else {
                        $roleInput = ucwords(strtolower($roleInput));
                    }
                }

                $branchInput = null;
                if (! empty($row['branch'])) {
                    $branchInput = trim($row['branch']);
                    $branchInput = ucwords(strtolower($branchInput));
                }

                try {
                    User::create([
                        'name' => $row['full_name'],
                        'email' => $row['email'],
                        'username' => $row['username'],
                        'phone' => ! empty($row['phone']) ? $row['phone'] : null,
                        'password' => Hash::make($row['password']),
                        'role' => 'EMPLOYEE',
                        'department_role' => $roleInput,
                        'branch' => $branchInput,
                        'manager_id' => $managerId,
                        'is_active' => true,
                    ]);
                } catch (QueryException $e) {
                    DB::rollBack();

                    Log::error('Bulk employee creation failed on row '.$rowIndex, [
                        'message' => $e->getMessage(),
                    ]);

                    $field = 'unknown';
                    $errorMsg = 'Database error occurred.';

                    if (str_contains($e->getMessage(), 'UNIQUE constraint failed')) {
                        if (str_contains($e->getMessage(), 'users.username')) {
                            $field = 'username';
                            $errorMsg = 'Username already exists.';
                        } elseif (str_contains($e->getMessage(), 'users.email')) {
                            $field = 'email';
                            $errorMsg = 'Email already exists.';
                        } elseif (str_contains($e->getMessage(), 'users.phone')) {
                            $field = 'phone';
                            $errorMsg = 'Phone already exists.';
                        } else {
                            $errorMsg = 'A unique constraint was violated.';
                        }
                    }

                    return [
                        'success' => false,
                        'message' => 'Bulk creation failed',
                        'data' => [
                            'errors' => [
                                [
                                    'row' => $rowIndex,
                                    'errors' => [$errorMsg],
                                ],
                            ],
                        ],
                    ];
                }

                $createdCount++;
                $rowIndex++;
            }
            DB::commit();

            return [
                'success' => true,
                'message' => 'Employees uploaded successfully',
                'data' => [
                    'created_count' => $createdCount,
                ],
            ];
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Bulk employee creation failed', [
                'message' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
