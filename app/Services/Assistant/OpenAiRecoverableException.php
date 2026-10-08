<?php

namespace App\Services\Assistant;

use RuntimeException;

/** OpenAI errors that may succeed with another key, model, or retry. */
class OpenAiRecoverableException extends RuntimeException
{
}
