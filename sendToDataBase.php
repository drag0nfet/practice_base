<?php
// Настройки подключения к базе данных
$servername = "localhost";
$username = "Server1";
$password = "_pass1Server1";
$dbname = "based";

// Создание подключения
$conn = new mysqli($servername, $username, $password, $dbname);

// Проверка подключения
if ($conn->connect_error) {
    die("Ошибка подключения: " . $conn->connect_error);
}

// Функция для экспорта данных в CSV
function exportToCSV($conn) {
    $query = "SELECT * FROM newData";
    $result = $conn->query($query);

    if ($result->num_rows > 0) {
        $timestamp = date("Y-m-d_H-i-s");
        $filePath = "C:/Server/data/DB/downloads/DB/";

        // Проверяем, существует ли директория, если нет - создаем её
        if (!is_dir($filePath)) {
            mkdir($filePath, 0777, true);
        }

        $fileName = $filePath . "newData_" . $timestamp . ".csv";
        $file = fopen($fileName, "w");

        if ($file === false) {
            die("Ошибка при создании файла CSV.");
        }

        // Получение и запись заголовков
        $headers = $result->fetch_fields();
        $headerRow = [];
        foreach ($headers as $header) {
            $headerRow[] = $header->name;
        }
        fputcsv($file, $headerRow);

        // Запись данных
        while ($row = $result->fetch_assoc()) {
            fputcsv($file, $row);
        }

        fclose($file);
        echo "Данные успешно экспортированы в CSV файл: $fileName";
    } else {
        echo "Нет данных для экспорта.";
    }
}

// Проверка, отправлена ли форма
if (isset($_POST['done'])) {
    // Получение данных из формы
    $login = $_POST['login'];
    $pass = $_POST['pass'];
    
    $fileContent = "";

    // Проверка и обработка загруженного файла
    if (isset($_FILES['textfile']) && $_FILES['textfile']['error'] == UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['textfile']['tmp_name'];
        $fileName = $_FILES['textfile']['name'];
        $fileSize = $_FILES['textfile']['size'];
        $fileType = $_FILES['textfile']['type'];
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));

        // Проверка типа файла
        if ($fileExtension === 'txt') {
            $uploadFileDir = 'C:/Server/data/DB/downloads/';
            $dest_path = $uploadFileDir . 'd_' . $fileName;

            if (!is_dir($uploadFileDir)) {
                mkdir($uploadFileDir, 0777, true);
            }

            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                chmod($dest_path, 0777);
                echo "Файл успешно загружен.<br>";

                // Чтение содержимого файла
                $fileContent = file_get_contents($dest_path);
                echo "Содержимое файла:<br>";
                echo nl2br($fileContent);

            } else {
                echo "Произошла ошибка при перемещении загруженного файла.";
            }
        } else {
            echo "Загружаемый файл должен быть в формате .txt.";
        }
    } else {
        echo "Произошла ошибка при загрузке файла. Код ошибки: " . $_FILES['textfile']['error'];
    }

    // Подготовка и выполнение SQL-запроса для добавления логина и пароля
    if ($fileContent == "") {
        $stmt = $conn->prepare("INSERT INTO newData (Login, Passwrd) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE Passwrd = VALUES(Passwrd)");
        $stmt->bind_param("ss", $login, $pass);
    } else {
        $stmt = $conn->prepare("INSERT INTO newData (Login, Passwrd, Advanced) VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE Passwrd = VALUES(Passwrd), Advanced = VALUES(Advanced)");
        $stmt->bind_param("sss", $login, $pass, $fileContent);
    }

    if ($stmt->execute()) {
        echo "Данные успешно добавлены";
    } else {
        echo "Ошибка: " . $stmt->error;
    }

    $stmt->close();
}

// Проверка, нажата ли кнопка для экспорта в CSV
if (isset($_POST['export'])) {
    exportToCSV($conn);
}

// Проверка, нажата ли кнопка для импорта CSV
if (isset($_POST['import']) && isset($_FILES['csvfile']) && $_FILES['csvfile']['error'] == UPLOAD_ERR_OK) {
    $fileTmpPath = $_FILES['csvfile']['tmp_name'];
    $fileName = $_FILES['csvfile']['name'];
    $fileSize = $_FILES['csvfile']['size'];
    $fileType = $_FILES['csvfile']['type'];
    $fileNameCmps = explode(".", $fileName);
    $fileExtension = strtolower(end($fileNameCmps));

    if ($fileExtension === 'csv') {
        if (($handle = fopen($fileTmpPath, 'r')) !== false) {
            // Пропуск заголовка
            fgetcsv($handle);

            while (($data = fgetcsv($handle, 1000, ",")) !== false) {
                $stmt = $conn->prepare("INSERT INTO newData (Login, Passwrd, Advanced) VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE Passwrd = VALUES(Passwrd), Advanced = VALUES(Advanced)");
                $stmt->bind_param("sss", $data[0], $data[1], $data[2]);

                if (!$stmt->execute()) {
                    echo "Ошибка: " . $stmt->error;
                }
                $stmt->close();
            }
            fclose($handle);
            echo "Данные успешно импортированы из CSV файла.";
        } else {
            echo "Не удалось открыть CSV файл.";
        }
    } else {
        echo "Загружаемый файл должен быть в формате .csv.";
    }
}

$conn->close();
?>