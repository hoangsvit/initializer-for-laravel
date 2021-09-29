<?php

namespace Domains\Readme;

use function collect;
use Domains\CreateProjectForm\CreateProjectForm;
use Domains\InitializationScript\InitializationScriptGenerator;
use Domains\PostDownload\PostDownloadTask;
use Domains\PostDownload\PostDownloadTaskGroup;
use Domains\PostDownload\PostDownloadTaskGroupCreator;
use Domains\PostDownload\PostInitializationLinkResolver;
use Domains\Readme\Support\Str;
use Illuminate\Contracts\View\Factory;
use function route;

/**
 * Generates a nice README.md at the project root.
 */
class ReadmeGenerator
{
    private string $template = 'template::README';

    public function __construct(
        private Factory $view,
        private PostDownloadTaskGroupCreator $postDownloadTaskGroupCreator,
        private MarkdownRenderer $markdown,
        private InitializationScriptGenerator $initializationScriptGenerator,
        private PostInitializationLinkResolver $postInitializationLinkResolver,
    ) {
    }

    public function render(CreateProjectForm $form): string
    {
        $meta = $form->metadata;

        return $this->view->make($this->template, [
            'title' => $meta->fullName(),
            'description' => $meta->description,
            'todos' => $this->renderTodos(
                $this->postDownloadTaskGroupCreator->fromForm($form),
            ),
            'links' => $this->postInitializationLinkResolver->links($form),
            'initializerUrl' => route('root'),
            'initializationScript' => $this->initializationScriptGenerator->scriptName(),
            'markdown' => $this->markdown,
        ])->render();
    }

    /**
     * @param  PostDownloadTaskGroup[]  $taskGroups
     */
    private function renderTodos(array $taskGroups): string
    {
        return collect($taskGroups)
            // If a group defines no task, we assume it does not need to be
            // included.
            ->filter(function (PostDownloadTaskGroup $group) {
                return count($group->tasks()) > 0;
            })
            ->map(function (PostDownloadTaskGroup $group) {
                $todoTitle = $this->markdown->bold($group->title());
                $todoBody = $this->renderGroupSubTasks($group->tasks());

                return join(PHP_EOL, [
                    $todoTitle,
                    Str::indentLines($todoBody, tabSize: 2),
                ]);
            })
            ->join(PHP_EOL.PHP_EOL);
    }

    /** @param PostDownloadTask[]|string[] $tasks */
    private function renderGroupSubTasks(array $tasks): string
    {
        return count($tasks) === 1
            ? $this->markdown->codeBlock($this->unwrapTask($tasks[0]), 'shell')
            : collect($tasks)
                ->map(function (PostDownloadTask|string $task) {
                    return $this->markdown->listItem(
                        $this->markdown->code($this->unwrapTask($task))
                    );
                })
                ->join(PHP_EOL);
    }

    private function unwrapTask(PostDownloadTask|string $task): string
    {
        return is_string($task) ? $task : $task->shell();
    }
}
