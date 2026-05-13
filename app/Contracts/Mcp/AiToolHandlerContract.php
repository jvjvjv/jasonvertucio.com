<?php

namespace App\Contracts\Mcp;

/**
 * Contract for AI chat bot MCP tool handlers.
 *
 * Implement this interface to create a custom tool that the AI model
 * can invoke during a conversation. Tools are auto-discovered from
 * the app/Services/Mcp/Tools/ChatBot/ directory.
 *
 * == Overview ==
 * Each tool has four required methods:
 *   1. name()      — unique identifier used by the AI to call the tool
 *   2. description() — human-readable description shown to the AI model
 *   3. schema()    — JSON Schema (input_schema) defining expected parameters
 *   4. handle()    — executes the tool logic and returns results
 *
 * == Tool Discovery ==
 * Tools are discovered automatically from app/Services/Mcp/Tools/ChatBot/
 * by ChatBotToolRegistry. Any class in that directory implementing this
 * interface is registered without additional configuration.
 *
 * == Available Dependencies ==
 * The registry injects the following context when instantiating tools:
 *   - conversation   : AiConversation instance
 *   - resumeDataService: ResumeDataServiceContract instance
 *   - memoryService  : AiMemoryService instance
 *   - targetedService: TargetedResumeService instance
 *   - userId         : ?int (conversation user_id)
 *
 * Access these via app() or dependency injection within handle().
 *
 * == Schema Format ==
 * The schema() method must return a JSON Schema (draft 2020-12) input_schema
 * object as a PHP array. The AI model uses this to know what parameters to
 * pass when calling the tool.
 *
 * Supported JSON Schema types and keywords:
 *   - type: "string", "number", "integer", "boolean", "array", "object"
 *   - properties: associative array of property definitions
 *   - required: array of required property names
 *   - description: human-readable description for each property
 *   - enum: array of allowed values
 *   - items: schema for array items
 *   - minimum/maximum: for integer/number types
 *   - additionalProperties: set to false to disallow extra properties
 *
 * Example schema:
 *     [
 *         'type' => 'object',
 *         'properties' => [
 *             'query' => [
 *                 'type' => 'string',
 *                 'description' => 'The search query string',
 *             ],
 *             'max_results' => [
 *                 'type' => 'integer',
 *                 'description' => 'Maximum number of results',
 *                 'minimum' => 1,
 *                 'maximum' => 50,
 *             ],
 *         ],
 *         'required' => ['query'],
 *     ]
 *
 * == Return Format ==
 * The handle() method returns an array that gets JSON-encoded
 * and sent back to the AI model as the tool result. Use the 'error'
 * key to signal failures (the AI will interpret it and can retry).
 *
 * Recommended return keys:
 *   - 'result' or 'data' : primary success output
 *   - 'error'            : indicates failure (AI can retry with different params)
 *   - 'metadata'         : optional extra info (timing, counts, etc.)
 */
interface AiToolHandlerContract
{
    /**
     * Return the unique tool name.
     *
     * This name is how the AI model identifies and calls the tool.
     * Use snake_case naming convention (e.g., 'web_search', 'get_site_info').
     * Must be unique across all registered tools.
     *
     * @return string Tool name (e.g., 'web_search')
     */
    public function name(): string;

    /**
     * Return a human-readable description of what the tool does.
     *
     * IMPORTANT: This description is sent to the AI model and influences
     * whether it chooses to call this tool. Be specific about:
     *   - What the tool does (its purpose)
     *   - When it should be used (triggering conditions)
     *   - What it returns (output format)
     *
     * Good examples:
     *   - "Search the web for current information on a topic."
     *   - "Get the user's profile information from their resume."
     *
     * Bad examples:
     *   - "Does stuff." (too vague)
     *   - "Tool." (not descriptive)
     *
     * @return string Description shown to the AI model
     */
    public function description(): string;

    /**
     * Return the JSON Schema (input_schema) defining expected parameters.
     *
     * This schema tells the AI model what arguments to pass when invoking
     * the tool. It follows JSON Schema (draft 2020-12) format.
     *
     * Required structure:
     *   [
     *       'type' => 'object',           // Always 'object' for tool inputs
     *       'properties' => [...],         // Parameter definitions
     *       'required' => [...],           // Array of required parameter names
     *   ]
     *
     * Property schema format:
     *   'property_name' => [
     *       'type'        => 'string',    // string|number|integer|boolean|array|object
     *       'description' => '...',       // Explains what this param does (helps AI decide when to use)
     *       // Optional constraints:
     *       'enum'        => ['a', 'b'], // For strings: allowed values
     *       'minimum'     => 0,           // For numbers/integers: minimum value
     *       'maximum'     => 100,         // For numbers/integers: maximum value
     *       'items'       => [...],       // For arrays: schema of array items
     *   ]
     *
     * Best practices:
     *   - Always include 'description' on every property to help the AI
     *   - Use 'required' to mark truly mandatory parameters
     *   - Use 'enum' for constrained sets of values
     *   - Keep the schema minimal — only include what the tool actually needs
     *
     * @return array<string, mixed> JSON Schema input_schema as PHP array
     */
    public function schema(): array;

    /**
     * Execute the tool logic with the given input parameters.
     *
     * This method is called by the AI framework when the model decides to
     * use this tool. The $input array contains the parameters the AI model
     * determined based on the conversation context and the tool's schema.
     *
     * @param array<string, mixed> $input Tool parameters as determined by the AI model.
     *                                    Keys match the 'properties' keys from schema().
     *                                    Values are coerced to the types specified in schema().
     *                                    Example: ['query' => 'weather today', 'max_results' => 5]
     * @return array<string, mixed> Result data that gets JSON-encoded and returned
     *                              to the AI model. Use 'error' key for failure cases.
     *                              Recommended structure:
     *                              [
     *                                  'result' => [...],     // Primary success output
     *                                  'metadata' => [...],  // Optional extra info
     *                              ]
     *                              Or on error:
     *                              [
     *                                  'error' => 'Description of what went wrong',
     *                              ]
     */
    public function handle(array $input): array;
}
