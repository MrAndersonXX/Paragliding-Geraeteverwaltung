param(
    [string] $Destination = '\\DS1\docker\glider-manager_dev',
    [switch] $DryRun
)

$ErrorActionPreference = 'Stop'
$sourceRoot = Split-Path -Parent $PSScriptRoot
$codeDirectories = @('public', 'src', 'config', 'docker', 'scripts', '.github')
$rootFiles = @(
    'CHANGELOG.md',
    'README.md',
    'VERSION',
    'composer.json',
    'composer.lock',
    'docker-compose.yml',
    'docker-compose.synology.yml',
    'docker-compose.synology.dev.yml',
    'docker-compose.synology.build.yml'
)

function Invoke-Robocopy {
    param(
        [string] $Source,
        [string] $Target,
        [string[]] $Arguments
    )

    $allArguments = @($Arguments)
    if ($DryRun) {
        $allArguments += '/L'
    }
    & robocopy $Source $Target $allArguments
    $exitCode = $LASTEXITCODE
    if ($exitCode -gt 7) {
        throw "Robocopy failed with exit code $exitCode while syncing $Source to $Target."
    }
}

if (!$DryRun) {
    New-Item -ItemType Directory -Path $Destination -Force | Out-Null
    New-Item -ItemType Directory -Path (Join-Path $Destination 'storage') -Force | Out-Null
}

foreach ($directory in $codeDirectories) {
    $source = Join-Path $sourceRoot $directory
    if (!(Test-Path $source)) {
        continue
    }
    $target = Join-Path $Destination $directory
    Invoke-Robocopy -Source $source -Target $target -Arguments @('/MIR', '/XD', 'storage', 'data', 'vendor', '.git', '/XF', '.env', '/R:2', '/W:2', '/NFL', '/NDL', '/NJH', '/NJS', '/NP')
}

foreach ($file in $rootFiles) {
    $source = Join-Path $sourceRoot $file
    if (!(Test-Path $source)) {
        continue
    }
    if ($DryRun) {
        Write-Host "Would copy $source to $Destination"
        continue
    }
    Copy-Item -Path $source -Destination $Destination -Force
}

$devCompose = Join-Path $Destination 'docker-compose.synology.dev.yml'
$composeYaml = Join-Path $Destination 'compose.yaml'
if ($DryRun) {
    Write-Host "Would copy $devCompose to $composeYaml"
} elseif (Test-Path $devCompose) {
    Copy-Item -Path $devCompose -Destination $composeYaml -Force
}

Write-Host "Development code sync complete: $Destination"
Write-Host 'The storage folder was created if missing and was not mirrored from the source workspace.'