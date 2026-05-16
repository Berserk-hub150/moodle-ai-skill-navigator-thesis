$urls = @(
    "http://localhost:8080/local/aiskillnavigator/index.php",
    "http://localhost:8080/local/aiskillnavigator/tutor.php?courseid=1",
    "http://localhost:8080/local/aiskillnavigator/quizgenerator.php?courseid=1",
    "http://localhost:8080/local/aiskillnavigator/mindmapgenerator.php?courseid=1",
    "http://localhost:8080/local/aiskillnavigator/scenariogenerator.php?courseid=1"
)

foreach ($url in $urls) {
    Write-Host $url
}