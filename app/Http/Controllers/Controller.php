<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: "1.0.0",
    title: "MSC Backend API Documentation",
    description: "Interactive Swagger API documentation for MSC Backend",
    contact: new OA\Contact(name: "MSC Support")
)]
#[OA\Server(
    url: "/api",
    description: "API Base URL"
)]
#[OA\SecurityScheme(
    securityScheme: "bearerAuth",
    type: "http",
    scheme: "bearer",
    description: "Enter ONLY your personal access token (e.g., 1|xxxx...)"
)]
abstract class Controller
{
    //
}
