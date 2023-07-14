<?php

namespace Bellows\Plugins;

use Bellows\PluginSdk\Contracts\Installable;
use Bellows\PluginSdk\Contracts\Repository;
use Bellows\PluginSdk\Facades\Console;
use Bellows\PluginSdk\Facades\Project;
use Bellows\PluginSdk\Plugin;
use Bellows\PluginSdk\PluginResults\CanBeInstalled;
use Bellows\PluginSdk\PluginResults\InstallationResult;
use Bellows\PluginSdk\PluginResults\InteractsWithRepositories;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

class GitHub extends Plugin implements Installable, Repository
{
    use CanBeInstalled, InteractsWithRepositories;

    public function getName(): string
    {
        return 'GitHub';
    }

    public function install(): ?InstallationResult
    {
        return InstallationResult::create()->wrapUp($this->installationWrapUp(...));
    }

    public function installationWrapUp(): void
    {
        if (!Console::confirm('Initialize a GitHub repo?', true)) {
            return;
        }

        Process::runWithOutput('git init');
        Process::runWithOutput('git add .');
        Process::runWithOutput('git commit -m "kickoff"');
        Process::runWithOutput('git branch -M main');

        $ghInstalled = trim(Process::run('which gh')->output()) !== '';

        if (!$ghInstalled) {
            Console::warn('GitHub CLI is not installed. Cannot create remote repository on GitHub.');
            Console::info('Install here: https://cli.github.com/');

            return;
        }

        $username = $this->getUsername();

        if ($username) {
            $repo = $username . '/' . Str::slug(Project::appName());
        }

        $githubRepo = Console::ask('GitHub repo name', $repo ?? null);

        $repoVisiblitity = Console::choice(
            'Repo visibility',
            ['public', 'private'],
            'private'
        );

        Process::runWithOutput("gh repo create {$githubRepo} --{$repoVisiblitity}");
        Process::runWithOutput("git remote add origin git@github.com:{$githubRepo}.git");
        Process::runWithOutput('git push -u origin main');
    }

    protected function getUsername(): string
    {
        if ($gitUserName = Process::run('git config --global user.username')->output()) {
            return $gitUserName;
        }

        $ghCliConfigPath = env('HOME') . '/.config/gh/hosts.yml';

        if (file_exists($ghCliConfigPath)) {
            $ghInfo = Yaml::parseFile($ghCliConfigPath);

            if ($username = $ghInfo['github.com']['user'] ?? null) {
                return $username;
            }
        }

        return null;
    }
}
