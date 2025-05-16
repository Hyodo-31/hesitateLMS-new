<?php
    require "../dbc.php";
    $type = $_GET['type'];
    $output = "";

    if($type == "specific"){
        get_studentlist_all();
    }else if($type == "class"){
        get_studentlist_class();
    }else if($type == "group"){
        get_studentlist_group();
    }

    // 学生リストを取得
    function get_studentlist_all(){
        global $conn, $output;
        $getStudentsQuery = "SELECT uid, Name FROM students WHERE 1 ";
        $getStudents = $conn->prepare($getStudentsQuery);
        $getStudents->execute();
        $students = $getStudents->get_result();
        if ($students->num_rows > 0) {
            while ($student = $students->fetch_assoc()) {
                $output.= "<input type='checkbox' name='students[]' value='{$student['uid']}'>{$student['Name']}<br>";
            }
        }
    }


    function get_studentlist_class(){
        global $conn, $output;
        $getStudentsQuery = "SELECT ClassTeacher.ClassID,classes.ClassName
        FROM ClassTeacher
        LEFT JOIN classes ON ClassTeacher.ClassID = classes.ClassID
        WHERE ClassTeacher.TID = ?";
        $getStudents = $conn->prepare($getStudentsQuery);
        $getStudents->bind_param('i', $_SESSION['MemberID']);
        $getStudents->execute();
        $students = $getStudents->get_result();
        if ($students->num_rows > 0) {
            while ($student = $students->fetch_assoc()) {
                $output.= "<input type='checkbox' name='students[]' value='{$student['ClassID']}'>{$student['ClassName']}<br>";
            }
        }
    }

    function get_studentlist_group(){
        global $conn, $output;
        $getStudentsQuery = "SELECT groups.group_id,groups.group_name
        FROM `groups`
        WHERE TID = ?";
        $getStudents = $conn->prepare($getStudentsQuery);
        $getStudents->bind_param('i', $_SESSION['MemberID']);
        $getStudents->execute();
        $students = $getStudents->get_result();
        if ($students->num_rows > 0) {
            while ($student = $students->fetch_assoc()) {
                $output.= "<input type='checkbox' name='students[]' value='{$student['group_id']}'>{$student['group_name']}<br>";
            }
        }
    }

    echo $output;
?>