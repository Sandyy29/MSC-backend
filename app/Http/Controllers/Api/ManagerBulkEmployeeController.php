<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EmployeeBulkUploadService;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ManagerBulkEmployeeController extends Controller
{
    protected $bulkUploadService;

    public function __construct(EmployeeBulkUploadService $bulkUploadService)
    {
        $this->bulkUploadService = $bulkUploadService;
    }

    #[OA\Post(
        path: '/manager/employees/bulk/validate',
        summary: 'Validate bulk employee upload',
        security: [['bearerAuth' => []]],
        tags: ['Manager Management'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['file'],
                    properties: [
                        new OA\Property(property: 'file', type: 'string', format: 'binary', description: 'CSV or XLSX file'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Validation results'),
            new OA\Response(response: 422, description: 'Invalid file format or validation error'),
        ]
    )]
    public function validateBulk(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:5120', // Max 5MB
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension());

        if (! in_array($extension, ['csv', 'xlsx'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid file extension ('.$extension.'). Please upload a CSV or XLSX file.',
            ], 422);
        }

        $filePath = $file->getRealPath();

        $results = $this->bulkUploadService->validateFile($filePath, $extension);

        if (! $results['success']) {
            return response()->json([
                'message' => $results['message'],
                'errors' => $results['data']['errors'] ?? [],
            ], 422);
        }

        return response()->json($results, 200);
    }

    #[OA\Post(
        path: '/manager/employees/bulk',
        summary: 'Process bulk employee upload',
        security: [['bearerAuth' => []]],
        tags: ['Manager Management'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['file'],
                    properties: [
                        new OA\Property(property: 'file', type: 'string', format: 'binary', description: 'CSV or XLSX file'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Upload successful'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function storeBulk(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:5120',
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension());

        if (! in_array($extension, ['csv', 'xlsx'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid file extension ('.$extension.'). Please upload a CSV or XLSX file.',
            ], 422);
        }

        $filePath = $file->getRealPath();
        $managerId = $request->user()->id;

        $results = $this->bulkUploadService->processFile($filePath, $extension, $managerId);

        if (! $results['success']) {
            return response()->json([
                'message' => $results['message'],
                'errors' => $results['data']['errors'] ?? [],
            ], 422);
        }

        return response()->json($results, 200);
    }

    #[OA\Get(
        path: '/manager/employees/bulk/template',
        summary: 'Download bulk employee upload template',
        security: [['bearerAuth' => []]],
        tags: ['Manager Management'],
        responses: [
            new OA\Response(response: 200, description: 'Template downloaded'),
        ]
    )]
    public function downloadTemplate()
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="employee_bulk_upload_template.csv"',
        ];

        $columns = ['full_name', 'username', 'email', 'phone', 'role', 'branch', 'password', 'confirm_password'];

        $callback = function () use ($columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            // Sample row
            fputcsv($file, ['John Doe', 'johndoe1', 'john@example.com', '1234567890', 'Software Developer', 'Chennai', 'password123', 'password123']);
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
