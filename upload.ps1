$ftpServer = "ftp://ftpupload.net/htdocs/"
$ftpUsername = "if0_42501853"
$ftpPassword = "BJ4OjCaHXt"
$localFolder = "e:\project coding\FinancePro-fixed (2)\FinancePro-fixed\FinancePro-fixed"

# Files/folders to EXCLUDE from upload (dev-only)
$excludePatterns = @('\.git', 'upload.ps1', 'run_migration.php', 'docs', 'README.md')

function Should-Exclude($path) {
    foreach ($pattern in $excludePatterns) {
        if ($path -match $pattern) { return $true }
    }
    return $false
}

Write-Host ""
Write-Host "=============================================" -ForegroundColor Cyan
Write-Host "  FinancePro - InfinityFree Robust FTP Deploy" -ForegroundColor Cyan
Write-Host "=============================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Server : ftpupload.net" -ForegroundColor Gray
Write-Host "User   : $ftpUsername" -ForegroundColor Gray
Write-Host "Target : /htdocs/" -ForegroundColor Gray
Write-Host ""

$successCount = 0
$failCount = 0

# 1. Create directories first
Write-Host "[1/3] Creating remote directories..." -ForegroundColor Yellow
$folders = Get-ChildItem -Path $localFolder -Recurse -Directory | Where-Object { -not (Should-Exclude $_.FullName) }
foreach ($folder in $folders) {
    $relativePath = $folder.FullName.Substring($localFolder.Length + 1).Replace('\', '/')
    $remoteFolderUrl = "$ftpServer$relativePath/"

    try {
        $request = [System.Net.FtpWebRequest]::Create($remoteFolderUrl)
        $request.Method = [System.Net.WebRequestMethods+Ftp]::MakeDirectory
        $request.Credentials = New-Object System.Net.NetworkCredential($ftpUsername, $ftpPassword)
        $request.Timeout = 10000
        $request.KeepAlive = $false # Close connection quickly to avoid limit hits
        $response = $request.GetResponse()
        $response.Close()
        Write-Host "  + Created: $relativePath" -ForegroundColor Green
        Start-Sleep -Milliseconds 200 # Rate limiting buffer
    } catch {
        # Folder probably already exists, that's fine
        Write-Host "  ~ Exists:  $relativePath" -ForegroundColor DarkGray
    }
}

# 2. Ensure uploads directories exist
Write-Host ""
Write-Host "[2/3] Ensuring uploads directories exist..." -ForegroundColor Yellow
$uploadDirs = @("uploads", "uploads/profile", "uploads/logos")
foreach ($dir in $uploadDirs) {
    $remoteFolderUrl = "$ftpServer$dir/"
    try {
        $request = [System.Net.FtpWebRequest]::Create($remoteFolderUrl)
        $request.Method = [System.Net.WebRequestMethods+Ftp]::MakeDirectory
        $request.Credentials = New-Object System.Net.NetworkCredential($ftpUsername, $ftpPassword)
        $request.Timeout = 10000
        $request.KeepAlive = $false
        $response = $request.GetResponse()
        $response.Close()
        Write-Host "  + Created: $dir" -ForegroundColor Green
        Start-Sleep -Milliseconds 200
    } catch {
        Write-Host "  ~ Exists:  $dir" -ForegroundColor DarkGray
    }
}

# 3. Upload files with robust retries
Write-Host ""
Write-Host "[3/3] Uploading files (with retry logic)..." -ForegroundColor Yellow
$files = Get-ChildItem -Path $localFolder -Recurse -File | Where-Object { -not (Should-Exclude $_.FullName) }
$totalFiles = $files.Count
$currentFile = 0

foreach ($file in $files) {
    $currentFile++
    $relativePath = $file.FullName.Substring($localFolder.Length + 1).Replace('\', '/')
    $remoteFileUrl = "$ftpServer$relativePath"
    $percent = [math]::Round(($currentFile / $totalFiles) * 100)
    
    $uploaded = $false
    $retries = 3
    $attempt = 1

    while (-not $uploaded -and $attempt -le $retries) {
        try {
            $request = [System.Net.FtpWebRequest]::Create($remoteFileUrl)
            $request.Method = [System.Net.WebRequestMethods+Ftp]::UploadFile
            $request.Credentials = New-Object System.Net.NetworkCredential($ftpUsername, $ftpPassword)
            $request.Timeout = 15000
            $request.KeepAlive = $false # Keep connection count low
            
            $fileBytes = [System.IO.File]::ReadAllBytes($file.FullName)
            $request.ContentLength = $fileBytes.Length
            
            $requestStream = $request.GetRequestStream()
            $requestStream.Write($fileBytes, 0, $fileBytes.Length)
            $requestStream.Close()
            
            $response = $request.GetResponse()
            $response.Close()

            $sizeKB = [math]::Round($file.Length / 1024, 1)
            Write-Host "  [$percent%] Uploaded: $relativePath ($sizeKB KB)" -ForegroundColor Green
            $successCount++
            $uploaded = $true
            Start-Sleep -Milliseconds 250 # Gentle delay between files to avoid rate limiting
        } catch {
            Write-Host "  [$percent%] Attempt $attempt failed for ${relativePath}: $($_.Exception.Message)" -ForegroundColor Yellow
            $attempt++
            if ($attempt -le $retries) {
                Write-Host "  Waiting 3 seconds before retry..." -ForegroundColor DarkYellow
                Start-Sleep -Seconds 3
            }
        }
    }

    if (-not $uploaded) {
        Write-Host "  [$percent%] PERMANENT FAILURE: $relativePath" -ForegroundColor Red
        $failCount++
    }
}

# Summary
Write-Host ""
Write-Host "=============================================" -ForegroundColor Cyan
Write-Host "  Deployment Summary" -ForegroundColor Cyan
Write-Host "=============================================" -ForegroundColor Cyan
Write-Host "  Uploaded : $successCount files" -ForegroundColor Green
if ($failCount -gt 0) {
    Write-Host "  Failed   : $failCount files" -ForegroundColor Red
} else {
    Write-Host "  Failed   : 0 files" -ForegroundColor Green
}
Write-Host ""
if ($failCount -eq 0) {
    Write-Host "  DEPLOYMENT SUCCESSFUL!" -ForegroundColor Green
} else {
    Write-Host "  Some files failed. Re-run this script to retry." -ForegroundColor Yellow
}
Write-Host ""
