<?php
/**
 * دفترچه یادداشت دیجیتال محرمانه دانش‌آموز
 */

require_once '../includes/header.php';
check_auth(['student', 'admin']);

try {
    // پیدا کردن اطلاعات دانش‌آموز
    $stmtS = $pdo->prepare("SELECT s.id, s.grade_level, cs.class_id 
        FROM students s 
        LEFT JOIN class_student cs ON s.id = cs.student_id
        WHERE s.user_id = ?");
    $stmtS->execute([$_SESSION['user_id']]);
    $student = $stmtS->fetch();

    if (!$student) {
        die("اطلاعات دانش‌آموز یافت نشد.");
    }

    $classId = $student['class_id'];
    $gradeLevel = $student['grade_level'];

    // واکشی دروس و مباحث جهت ساخت ساختار درختی (Tree Structure)
    $stmtCourses = $pdo->prepare("SELECT ctc.id as allocation_id, co.id as course_id, co.course_name 
        FROM class_teacher_course ctc
        JOIN courses co ON ctc.course_id = co.id
        WHERE ctc.class_id = ?");
    $stmtCourses->execute([$classId]);
    $courses = $stmtCourses->fetchAll();

    $treeData = [];
    foreach ($courses as $course) {
        $stmtTopics = $pdo->prepare("SELECT id, topic_title FROM topics WHERE class_teacher_course_id = ?");
        $stmtTopics->execute([$course['allocation_id']]);
        $topics = $stmtTopics->fetchAll();
        
        $treeData[] = [
            'course' => $course,
            'topics' => $topics
        ];
    }

} catch (PDOException $e) {
    die("خطا در لود درخت مباحث یادداشت: " . $e->getMessage());
}
?>

<div class="container-fluid">
    <div class="row g-4">
        <!-- سایدبار دفترچه یادداشت (سمت راست) -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-3">
                <ul class="nav nav-tabs custom-tabs px-3 bg-light border-bottom-0" id="notepadTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general-pane" type="button" role="tab" aria-controls="general-pane" aria-selected="true">
                            <i class="bi bi-journal-text"></i> یادداشت‌های عمومی
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="course-tab" data-bs-toggle="tab" data-bs-target="#course-pane" type="button" role="tab" aria-controls="course-pane" aria-selected="false">
                            <i class="bi bi-diagram-3-fill"></i> یادداشت‌های درسی
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="notepadTabsContent">
                    <!-- تب یادداشت‌های عمومی -->
                    <div class="tab-pane fade show active p-3" id="general-pane" role="tabpanel" aria-labelledby="general-tab">
                        <button class="btn btn-primary btn-sm w-100 fw-bold mb-3" onclick="createNewNote('general')">
                            <i class="bi bi-plus-lg me-1"></i> ایجاد یادداشت عمومی جدید
                        </button>
                        
                        <div class="list-group list-group-flush" id="generalNotesList" style="max-height: 400px; overflow-y: auto;">
                            <!-- با ایجکس پر می‌شود -->
                        </div>
                    </div>

                    <!-- تب یادداشت‌های درسی (ساختار درختی) -->
                    <div class="tab-pane fade p-3" id="course-pane" role="tabpanel" aria-labelledby="course-tab">
                        <p class="text-secondary small mb-3">برای یادداشت‌برداری علمی، موضوع مربوطه را از ساختار درختی زیر انتخاب کنید:</p>
                        
                        <div class="accordion accordion-flush border rounded" id="courseTreeAccordion" style="max-height: 400px; overflow-y: auto;">
                            <?php $cIdx = 0; foreach ($treeData as $node): $cIdx++; ?>
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="heading-c-<?= $cIdx ?>">
                                        <button class="accordion-button collapsed fw-bold small text-primary" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-c-<?= $cIdx ?>" aria-expanded="false" aria-controls="collapse-c-<?= $cIdx ?>">
                                            <i class="bi bi-book me-2"></i> <?= htmlspecialchars($node['course']['course_name']) ?>
                                        </button>
                                    </h2>
                                    <div id="collapse-c-<?= $cIdx ?>" class="accordion-collapse collapse" aria-labelledby="heading-c-<?= $cIdx ?>" data-bs-parent="#courseTreeAccordion">
                                        <div class="accordion-body p-0">
                                            <div class="list-group list-group-flush">
                                                <?php if (empty($node['topics'])): ?>
                                                    <span class="text-secondary small p-3 d-block text-center">مبحثی تعریف نشده است.</span>
                                                <?php else: foreach ($node['topics'] as $tp): ?>
                                                    <button type="button" class="list-group-item list-group-item-action ps-4 small d-flex justify-content-between align-items-center" onclick="selectTopicNode(<?= $node['course']['course_id'] ?>, <?= $tp['id'] ?>, '<?= htmlspecialchars($node['course']['course_name']) ?> / <?= htmlspecialchars($tp['topic_title']) ?>')">
                                                        <span><i class="bi bi-chevron-left me-1 text-secondary"></i> <?= htmlspecialchars($tp['topic_title']) ?></span>
                                                    </button>
                                                <?php endforeach; endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- پنجره ویرایشگر یادداشت (سمت چپ) -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0" id="editorTitle">یادداشت جدید</h5>
                    <span class="badge bg-secondary" id="editorBadge">عمومی</span>
                </div>
                
                <div class="card-body">
                    <form id="noteForm">
                        <!-- فیلدهای پنهان نگهداری وضعیت یادداشت فعال -->
                        <input type="hidden" id="noteId" name="id" value="0">
                        <input type="hidden" id="noteType" name="note_type" value="general">
                        <input type="hidden" id="noteCourseId" name="course_id" value="">
                        <input type="hidden" id="noteTopicId" name="topic_id" value="">

                        <div class="mb-3">
                            <label class="form-label">عنوان یادداشت *</label>
                            <input type="text" id="noteTitleInput" name="title" class="form-control" placeholder="مثال: خلاصه فرمول‌های توان و ریشه" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">متن یادداشت *</label>
                            <textarea id="noteContentInput" name="content" class="form-control" rows="10" placeholder="یادداشت علمی یا لیست کار خود را در این بخش بنویسید..." required></textarea>
                        </div>

                        <div class="d-flex justify-content-between">
                            <button type="button" class="btn btn-outline-danger fw-bold" id="deleteNoteBtn" onclick="deleteActiveNote()" style="display: none;">
                                <i class="bi bi-trash3-fill me-1"></i> حذف یادداشت
                            </button>
                            <button type="submit" class="btn btn-primary fw-bold px-5 ms-auto">
                                <i class="bi bi-floppy-fill me-1"></i> ذخیره یادداشت
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- بخش نمایش یادداشت‌های درسی ثبت شده بعد از انتخاب سرفصل -->
            <div class="card border-0 shadow-sm rounded-3 mt-4 d-none" id="topicNotesCard">
                <div class="card-header bg-light py-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-primary" id="topicNotesTitle">یادداشت‌های درسی مبحث</h6>
                    <button class="btn btn-primary btn-sm fw-bold" onclick="createNewCourseNote()"><i class="bi bi-plus-lg me-1"></i> یادداشت جدید برای این سرفصل</button>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush" id="topicNotesList">
                        <!-- با ایجکس پر می‌شود -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let activeNotesList = [];
    let currentActiveTopic = null; // {courseId, topicId, title}

    document.addEventListener('DOMContentLoaded', function() {
        loadNotesList('general'); // لود اولیه یادداشت‌های عمومی
    });

    // بارگذاری لیست یادداشت‌ها با AJAX
    function loadNotesList(type, courseId = 0, topicId = 0) {
        let url = `../ajax/notepad.php?action=list&note_type=${type}`;
        if (type === 'course') {
            url += `&course_id=${courseId}&topic_id=${topicId}`;
        }

        fetch(url)
            .then(res => res.json())
            .then(notes => {
                activeNotesList = notes;
                if (type === 'general') {
                    renderGeneralNotes(notes);
                } else {
                    renderTopicNotes(notes);
                }
            })
            .catch(err => console.error("خطا در واکشی یادداشت‌ها:", err));
    }

    function renderGeneralNotes(notes) {
        const container = document.getElementById('generalNotesList');
        container.innerHTML = '';
        
        if (notes.length === 0) {
            container.innerHTML = '<span class="text-secondary small p-3 text-center d-block">یادداشتی وجود ندارد.</span>';
            return;
        }

        notes.forEach(note => {
            const btn = document.createElement('button');
            btn.className = 'list-group-item list-group-item-action py-3 px-2 border-bottom text-start';
            btn.innerHTML = `
                <div class="fw-bold text-dark small mb-1">${note.title}</div>
                <p class="mb-0 text-secondary text-xs text-truncate" style="max-width: 250px;">${note.content}</p>
            `;
            btn.onclick = () => loadNoteIntoEditor(note);
            container.appendChild(btn);
        });
    }

    function renderTopicNotes(notes) {
        const container = document.getElementById('topicNotesList');
        container.innerHTML = '';

        if (notes.length === 0) {
            container.innerHTML = '<span class="text-secondary small p-3 text-center d-block">هنوز یادداشتی برای این مبحث ثبت نشده است.</span>';
            return;
        }

        notes.forEach(note => {
            const btn = document.createElement('button');
            btn.className = 'list-group-item list-group-item-action py-3 px-3 d-flex justify-content-between align-items-center';
            btn.innerHTML = `
                <div>
                    <strong class="text-dark small">${note.title}</strong>
                    <p class="mb-0 text-secondary text-truncate small mt-1" style="max-width: 400px;">${note.content}</p>
                </div>
                <i class="bi bi-chevron-left text-secondary"></i>
            `;
            btn.onclick = () => loadNoteIntoEditor(note);
            container.appendChild(btn);
        });
    }

    // لود یادداشت در ادیتور جهت ویرایش
    function loadNoteIntoEditor(note) {
        document.getElementById('noteId').value = note.id;
        document.getElementById('noteType').value = note.note_type;
        document.getElementById('noteCourseId').value = note.course_id || '';
        document.getElementById('noteTopicId').value = note.topic_id || '';
        
        document.getElementById('noteTitleInput').value = note.title;
        document.getElementById('noteContentInput').value = note.content;
        
        document.getElementById('editorTitle').innerText = 'ویرایش یادداشت';
        document.getElementById('editorBadge').innerText = note.note_type === 'general' ? 'عمومی' : 'درسی';
        document.getElementById('editorBadge').className = note.note_type === 'general' ? 'badge bg-secondary' : 'badge bg-primary';
        
        document.getElementById('deleteNoteBtn').style.display = 'inline-block';
    }

    // ایجاد یادداشت جدید عمومی
    function createNewNote(type = 'general') {
        document.getElementById('noteId').value = '0';
        document.getElementById('noteType').value = type;
        document.getElementById('noteCourseId').value = '';
        document.getElementById('noteTopicId').value = '';
        
        document.getElementById('noteTitleInput').value = '';
        document.getElementById('noteContentInput').value = '';
        
        document.getElementById('editorTitle').innerText = 'یادداشت جدید';
        document.getElementById('editorBadge').innerText = 'عمومی';
        document.getElementById('editorBadge').className = 'badge bg-secondary';
        
        document.getElementById('deleteNoteBtn').style.display = 'none';
    }

    // انتخاب مبحث درسی از درخت
    function selectTopicNode(courseId, topicId, fullPathTitle) {
        currentActiveTopic = { courseId, topicId, title: fullPathTitle };
        
        document.getElementById('topicNotesCard').classList.remove('d-none');
        document.getElementById('topicNotesTitle').innerText = 'یادداشت‌های درسی مبحث: ' + fullPathTitle;
        
        loadNotesList('course', courseId, topicId);
        createNewCourseNote(); // فرم را برای ثبت یادداشت در این شاخه آماده می‌کنیم
    }

    // آماده‌سازی فرم برای یادداشت در شاخه درسی
    function createNewCourseNote() {
        if (!currentActiveTopic) return;
        
        document.getElementById('noteId').value = '0';
        document.getElementById('noteType').value = 'course';
        document.getElementById('noteCourseId').value = currentActiveTopic.courseId;
        document.getElementById('noteTopicId').value = currentActiveTopic.topicId;
        
        document.getElementById('noteTitleInput').value = '';
        document.getElementById('noteContentInput').value = '';
        
        document.getElementById('editorTitle').innerText = 'یادداشت درسی جدید';
        document.getElementById('editorBadge').innerText = 'درسی';
        document.getElementById('editorBadge').className = 'badge bg-primary';
        
        document.getElementById('deleteNoteBtn').style.display = 'none';
    }

    // ذخیره یادداشت با ایجکس
    document.getElementById('noteForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const id = document.getElementById('noteId').value;
        const type = document.getElementById('noteType').value;
        const courseId = document.getElementById('noteCourseId').value;
        const topicId = document.getElementById('noteTopicId').value;
        const title = document.getElementById('noteTitleInput').value.trim();
        const content = document.getElementById('noteContentInput').value.trim();

        const formData = new FormData();
        formData.append('action', 'save');
        formData.append('id', id);
        formData.append('note_type', type);
        formData.append('course_id', courseId);
        formData.append('topic_id', topicId);
        formData.append('title', title);
        formData.append('content', content);

        fetch('../ajax/notepad.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                alert(res.message);
                if (type === 'general') {
                    loadNotesList('general');
                    createNewNote('general');
                } else {
                    loadNotesList('course', courseId, topicId);
                    createNewCourseNote();
                }
            } else {
                alert("خطا در ذخیره یادداشت: " + res.message);
            }
        })
        .catch(err => console.error("خطا در ذخیره:", err));
    });

    // حذف یادداشت فعال با ایجکس
    function deleteActiveNote() {
        const id = document.getElementById('noteId').value;
        const type = document.getElementById('noteType').value;
        const courseId = document.getElementById('noteCourseId').value;
        const topicId = document.getElementById('noteTopicId').value;

        if (id <= 0) return;
        if (!confirm('آیا از حذف این یادداشت اطمینان دارید؟')) return;

        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', id);

        fetch('../ajax/notepad.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                alert(res.message);
                if (type === 'general') {
                    loadNotesList('general');
                    createNewNote('general');
                } else {
                    loadNotesList('course', courseId, topicId);
                    createNewCourseNote();
                }
            } else {
                alert("خطا در حذف یادداشت: " + res.message);
            }
        })
        .catch(err => console.error("خطا در حذف:", err));
    }
</script>

<?php require_once '../includes/footer.php'; ?>
