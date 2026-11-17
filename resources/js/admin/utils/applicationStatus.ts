/** Statuses an application holds before it enters the pipeline. */
const PRE_PIPELINE_STATUSES = new Set(["draft", "passed"]);

/** Whether the application has entered the pipeline (`applied` onward). */
export function isPipelineStatus(status: string): boolean {
    return !PRE_PIPELINE_STATUSES.has(status);
}
