<?php

namespace App\Exceptions;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The one place the application-tracking domain's refusals become HTTP
 * responses, so no controller action catches them itself.
 */
class ApplicationExceptionRenderer
{
    /**
     * The client error each refusal maps to. Anything unlisted is a 422.
     *
     * @var array<class-string<ApplicationException>, int>
     */
    private const array STATUSES = [
        ApplicationAlreadyHasConversationException::class => Response::HTTP_CONFLICT,
        ApplicationInPipelineException::class => Response::HTTP_CONFLICT,
        TargetedResumeAlreadySentException::class => Response::HTTP_CONFLICT,
        TargetedResumeMissingException::class => Response::HTTP_NOT_FOUND,
        ApplicationStatusUpdateMismatchException::class => Response::HTTP_NOT_FOUND,
        TerminalApplicationException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        NonPipelineStatusException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        ResumeVersionUnavailableException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ];

    public function __construct(
        private ExceptionHandler $handler,
    ) {}

    /**
     * A JSON caller — or any caller of an `api/` endpoint, which answers in
     * JSON whatever it was asked for, the event-stream chat endpoint
     * included — gets `{ "message": … }` with the mapped status. An
     * Inertia visit that was refused for a reason the admin can act on (a
     * conflict or an unprocessable request) returns to the page it came
     * from with the reason as an `error` flash; anything else is the plain
     * HTTP error.
     */
    public function render(ApplicationException $exception, Request $request): Response
    {
        $status = $this->statusFor($exception);

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => $exception->getMessage()], $status);
        }

        if ($request->hasHeader('X-Inertia') && $status !== Response::HTTP_NOT_FOUND) {
            return redirect()->back()->with('error', $exception->getMessage());
        }

        return $this->handler->render($request, new HttpException($status, $exception->getMessage(), $exception));
    }

    public function statusFor(ApplicationException $exception): int
    {
        return self::STATUSES[$exception::class] ?? Response::HTTP_UNPROCESSABLE_ENTITY;
    }
}
