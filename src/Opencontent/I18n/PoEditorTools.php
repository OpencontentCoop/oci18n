<?php

namespace Opencontent\I18n;

class PoEditorTools
{
    public static function selectProject(PoEditorClient $client, string $default = null)
    {
        $projects = $client->getProjects();
        if ($default) {
            foreach ($projects as $project) {
                if ($project['name'] == $default) {
                    return $project;
                }
            }
        }
        $menu = new \ezcConsoleMenuDialog(new \ezcConsoleOutput());
        $menu->options = new \ezcConsoleMenuDialogOptions();
        $menu->options->text = "Please choose a project:\n";
        $menu->options->validator = new \ezcConsoleMenuDialogDefaultValidator(
            array_column($projects, 'name')
        );
        $choice = \ezcConsoleDialogViewer::displayDialog($menu);

        return $projects[$choice] ?? null;
    }
}