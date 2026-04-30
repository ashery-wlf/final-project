<?php
include("includes/auth.php");
include("includes/db.php");
include("includes/app.php");

ensureEventSchema($conn);

$user_id = (int) $_SESSION['user_id'];
$message = "";
$error = "";

if (isset($_POST['submit'])) {
    $name = trim($_POST['name'] ?? "");
    $description = trim($_POST['description'] ?? "");
    $date = $_POST['date'] ?? "";
    $time = $_POST['time'] ?? "";
    $end_time = $_POST['end_time'] ?? "";
    $attendance_start = $_POST['attendance_start'] ?? "";
    $attendance_end = $_POST['attendance_end'] ?? "";
    $registration_mode = $_POST['registration_mode'] ?? "self";
    $venue_name = trim($_POST['venue_name'] ?? "");
    $venue_location = trim($_POST['venue_location'] ?? "");
    $location_lat = trim($_POST['location_lat'] ?? "");
    $location_lng = trim($_POST['location_lng'] ?? "");
    $target_audience = trim($_POST['target_audience'] ?? "");
    $access_code = trim($_POST['access_code'] ?? "");
    $registrant_list = trim($_POST['registrant_list'] ?? "");

    if ($name === "" || $date === "" || $time === "") {
        $error = "Please fill the required event details.";
    } else {
        $imagePath = "logo.png";

        if (!empty($_FILES['image']['name'])) {
            if (!is_dir("uploads")) {
                mkdir("uploads", 0777, true);
            }

            $filename = time() . "_" . basename($_FILES['image']['name']);
            $targetPath = "uploads/" . preg_replace('/[^A-Za-z0-9._-]/', '_', $filename);

            if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                $imagePath = $targetPath;
            }
        }

        if ($registration_mode === "code" && $access_code === "") {
            $access_code = generateAccessCode();
        }

        $locationLatValue = is_numeric($location_lat) ? (float) $location_lat : null;
        $locationLngValue = is_numeric($location_lng) ? (float) $location_lng : null;
        $registrants = parseRegistrantLines($registrant_list);
        $invitedEmailsText = implode("\n", array_map(function ($item) {
            return $item['email'];
        }, $registrants));

        $stmt = $conn->prepare("
            INSERT INTO events(
                name, description, date, time, end_time, attendance_start, attendance_end,
                image, venue_name, venue_location, location_lat, location_lng, target_audience, created_by, type, registration_mode, access_code, invited_emails
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'online', ?, ?, ?)
        ");

        $stmt->bind_param(
            "ssssssssssddsisss",
            $name,
            $description,
            $date,
            $time,
            $end_time,
            $attendance_start,
            $attendance_end,
            $imagePath,
            $venue_name,
            $venue_location,
            $locationLatValue,
            $locationLngValue,
            $target_audience,
            $user_id,
            $registration_mode,
            $access_code,
            $invitedEmailsText
        );

        if ($stmt->execute()) {
            $eventId = $stmt->insert_id;
            registerUserToEvent($conn, $user_id, $eventId);
            if ($registration_mode === "code" && !empty($registrants)) {
                foreach ($registrants as $registrant) {
                    upsertPrivateRegistrant($conn, $eventId, $registrant);
                    sendEventAccessCodeEmail($registrant['name'], $registrant['email'], [
                        'name' => $name,
                        'date' => $date,
                        'time' => $time,
                        'venue_name' => $venue_name,
                    ], $access_code);
                }
            }
            header("Location: event.php?id=" . $eventId);
            exit();
        }

        $error = "Event could not be created right now.";
    }
}
?>
<?php
$pageCss = <<<'CSS'
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<style>
.back{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:12px 16px;
    border-radius:12px;
    background:#ffffff;
    border:1px solid #dbe5f1;
    color:#1d4ed8;
    font-weight:700;
}

.card{
    background:#fff;
    border:1px solid #e2e8f0;
    border-radius:22px;
    box-shadow:0 20px 40px rgba(15, 23, 42, 0.08);
    overflow:hidden;
}

.card-head{
    padding:22px 24px 0;
}

.card-head h2{
    margin:0;
    font-size:22px;
}

.card-head p{
    margin:8px 0 0;
    color:#64748b;
}

.form{
    padding:24px;
    display:grid;
    grid-template-columns:repeat(2, minmax(0, 1fr));
    gap:18px;
}

.field{
    display:flex;
    flex-direction:column;
    gap:8px;
}

.field.full{
    grid-column:1 / -1;
}

label{
    font-size:14px;
    font-weight:700;
    color:#334155;
}

input, textarea, select{
    width:100%;
    border:1px solid #dbe3ef;
    border-radius:14px;
    padding:13px 14px;
    font-size:14px;
    background:#f8fbff;
    color:#0f172a;
    outline:none;
}

textarea{
    min-height:120px;
    resize:vertical;
}

.inline-note{
    font-size:12px;
    color:#64748b;
}

.invite-box{
    grid-column:1 / -1;
    border:1px solid #dbe3ef;
    border-radius:18px;
    padding:16px;
    background:#f8fbff;
}

.mode-box{
    grid-column:1 / -1;
    display:grid;
    grid-template-columns:repeat(2, minmax(0, 1fr));
    gap:14px;
}

.mode-option{
    border:1px solid #dbe3ef;
    border-radius:18px;
    padding:16px;
    background:#f8fbff;
}

.mode-option strong{
    display:block;
    margin-bottom:6px;
}

.message, .error{
    margin:18px 24px 0;
    padding:14px 16px;
    border-radius:14px;
    font-weight:600;
}

.message{
    background:#ecfdf5;
    color:#166534;
}

.error{
    background:#fef2f2;
    color:#b91c1c;
}

.actions{
    grid-column:1 / -1;
    display:flex;
    justify-content:flex-end;
    gap:12px;
    padding-top:4px;
}

button{
    border:none;
    border-radius:14px;
    padding:14px 22px;
    font-size:15px;
    font-weight:800;
    cursor:pointer;
}

.primary{
    background:linear-gradient(180deg, #2563eb 0%, #1d4ed8 100%);
    color:#fff;
    box-shadow:0 16px 30px rgba(37, 99, 235, 0.28);
}

.secondary{
    background:#e2e8f0;
    color:#334155;
}

/* Modal Styles */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.modal-overlay.active {
    display: flex;
}

.modal-content {
    background: white;
    border-radius: 20px;
    padding: 24px;
    max-width: 500px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.modal-title {
    font-size: 20px;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #64748b;
    padding: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    transition: background 0.2s;
}

.modal-close:hover {
    background: #f1f5f9;
}

.modal-body {
    color: #334155;
}

.modal-form {
    display: flex;
    flex-direction: column;
    gap: 16px;
    margin-top: 16px;
}

.modal-field {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.modal-field label {
    font-size: 14px;
    font-weight: 600;
    color: #374151;
}

.modal-field textarea {
    border: 1px solid #d1d5db;
    border-radius: 8px;
    padding: 10px;
    font-size: 14px;
    resize: vertical;
    min-height: 100px;
}

.modal-field input {
    border: 1px solid #d1d5db;
    border-radius: 8px;
    padding: 10px;
    font-size: 14px;
}

.modal-help {
    font-size: 12px;
    color: #6b7280;
    margin-top: 4px;
}

.modal-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    margin-top: 20px;
}

.modal-btn {
    padding: 10px 16px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    transition: all 0.2s;
}

.modal-btn-primary {
    background: #2563eb;
    color: white;
}

.modal-btn-primary:hover {
    background: #1d4ed8;
}

.modal-btn-secondary {
    background: #f3f4f6;
    color: #374151;
}

.modal-btn-secondary:hover {
    background: #e5e7eb;
}

.location-tools,
.invite-tools{
    grid-column:1 / -1;
    border:1px solid #dce5f1;
    border-radius:18px;
    padding:16px;
    background:#f8fbff;
}

.location-toolbar{
    margin-top:12px;
    display:grid;
    grid-template-columns:minmax(0, 1fr) auto;
    gap:10px;
    align-items:center;
}

.ghost{
    background:#e0e7ff;
    color:#3730a3;
}

.inline-map{
    margin-top:14px;
    border-radius:16px;
    overflow:hidden;
    border:1px solid #dbe3ef;
    min-height:118px;
    background:#fff;
}

.inline-map #adminEventMap{
    width:100%;
    height:118px;
}

.leaflet-container{
    font:inherit;
}

.leaflet-control-zoom{
    transform:scale(0.92);
    transform-origin:top left;
}

@media (max-width: 760px){
    .form, .mode-box{
        grid-template-columns:1fr;
    }

    .location-toolbar{
        grid-template-columns:1fr;
    }

    .location-actions{
        width:100%;
    }

    .ghost-button{
        width:100%;
    }

    .modal-content {
        margin: 20px;
        width: calc(100% - 40px);
    }
}
</style>
CSS;

renderAppShellStart($conn, [
    "title" => "Create Event",
    "active" => "create-event",
    "page_title" => "Create Event",
    "page_subtitle" => "Set event time, attendance window, access method, venue information, and poster image in one place.",
    "search_placeholder" => "Search events...",
    "page_actions" => '<a href="dashboard.php" class="back">Back to dashboard</a>',
    "show_page_head" => false,
    "extra_head" => $pageCss,
]);
?>
    <div class="card">
        <div class="card-head">
            <h2>Phase One Event Setup</h2>
            <p>All users can see events online. Access to join depends on the mode you choose here.</p>
        </div>

        <?php if ($message !== ""): ?>
            <div class="message"><?php echo h($message); ?></div>
        <?php endif; ?>

        <?php if ($error !== ""): ?>
            <div class="error"><?php echo h($error); ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="form">
            <div class="field full">
                <label for="name">Event Name</label>
                <input id="name" type="text" name="name" required value="<?php echo h($_POST['name'] ?? ''); ?>">
            </div>

            <div class="field full">
                <label for="description">Event Description</label>
                <textarea id="description" name="description" placeholder="What is this event about?"><?php echo h($_POST['description'] ?? ''); ?></textarea>
            </div>

            <div class="field">
                <label for="date">Event Date</label>
                <input id="date" type="date" name="date" required value="<?php echo h($_POST['date'] ?? ''); ?>">
            </div>

            <div class="field">
                <label for="time">Start Time</label>
                <input id="time" type="time" name="time" required value="<?php echo h($_POST['time'] ?? ''); ?>">
            </div>

            <div class="field">
                <label for="end_time">End Time</label>
                <input id="end_time" type="time" name="end_time" value="<?php echo h($_POST['end_time'] ?? ''); ?>">
            </div>

            <div class="field">
                <label for="target_audience">Target Attendees</label>
                <input id="target_audience" type="text" name="target_audience" placeholder="Example: Final year students" value="<?php echo h($_POST['target_audience'] ?? ''); ?>">
            </div>

            <div class="field">
                <label for="attendance_start">Attendance Opens</label>
                <input id="attendance_start" type="time" name="attendance_start" value="<?php echo h($_POST['attendance_start'] ?? ''); ?>">
                <div class="inline-note">If empty, attendance opens at the event start time.</div>
            </div>

            <div class="field">
                <label for="attendance_end">Attendance Closes</label>
                <input id="attendance_end" type="time" name="attendance_end" value="<?php echo h($_POST['attendance_end'] ?? ''); ?>">
                <div class="inline-note">If empty, attendance closes at the end time.</div>
            </div>

            <div class="field">
                <label for="venue_name">Venue Name</label>
                <input id="venue_name" type="text" name="venue_name" placeholder="Example: Main Hall" value="<?php echo h($_POST['venue_name'] ?? ''); ?>">
            </div>

            
            <div class="location-tools">
                <label>Live Location Share</label>
                <p style="margin-top:8px;">Search the exact event place so attendees can zoom in and follow directions without getting lost.</p>
                <input id="location_lat" type="hidden" name="location_lat" value="<?php echo h($_POST['location_lat'] ?? ''); ?>">
                <input id="location_lng" type="hidden" name="location_lng" value="<?php echo h($_POST['location_lng'] ?? ''); ?>">
                <div class="location-toolbar">
                    <input id="locationSearch" type="text" placeholder="Search event venue or place name">
                    <div class="actions" style="margin-top:0;">
                        <button type="button" id="findLocation" class="ghost">🔍 Find on Map</button>
                        <button type="button" id="useMyLocation" class="ghost">📍 Use My Location</button>
                    </div>
                </div>
                <div id="locationStatus" class="message" style="display:block;margin-top:14px;background:#eff6ff;color:#1d4ed8;">
                    No event location selected yet.
                </div>
                <div class="inline-map">
                    <div id="adminEventMap"></div>
                </div>
            </div>

            <div class="field full">
                <label for="image">Venue / Event Poster</label>
                <input id="image" type="file" name="image" accept="image/*">
            </div>

            <div class="mode-box">
                <div class="mode-option">
                    <strong>Self Registration</strong>
                    <label><input type="radio" name="registration_mode" value="self" <?php echo (($_POST['registration_mode'] ?? 'self') === 'self') ? 'checked' : ''; ?>> People register themselves from the event page</label>
                </div>

                <div class="mode-option">
                    <strong>Access Code</strong>
                    <label><input type="radio" name="registration_mode" value="code" <?php echo (($_POST['registration_mode'] ?? '') === 'code') ? 'checked' : ''; ?>> People must enter a special code before joining</label>
                </div>
            </div>

            <div class="field full">
                <label for="access_code">Access Code</label>
                <input id="access_code" type="text" name="access_code" placeholder="Auto-generated for private events" value="<?php echo h($_POST['access_code'] ?? ''); ?>">
                <div class="inline-note">If this event is private and you leave it blank, the system creates the code automatically.</div>
            </div>

            <div class="invite-box">
                <label>Private Event Registrants</label>
                <div class="inline-note">Add specific people who can register for this private event.</div>
                <button type="button" class="ghost-button" id="openRegistrantModal" style="width: 100%; margin-top: 10px;">
                    + Add Private Registrants
                </button>
                <div id="registrantCount" class="inline-note" style="margin-top: 8px; display: none;">
                    <span id="registrantCountText">0</span> registrant(s) added
                </div>
            </div>

            <div class="actions">
                <a href="events.php" class="secondary" style="display:inline-flex;align-items:center;">Cancel</a>
                <button class="primary" type="submit" name="submit">Create Event</button>
            </div>
        </form>
    </div>

    <!-- Private Event Registrants Modal -->
    <div id="registrantModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add Private Event Registrants</h3>
                <button class="modal-close" id="closeModal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Add specific people who can register for this private event. They will receive the access code via email.</p>
                
                <div class="modal-form">
                    <div class="modal-field">
                        <label for="modalRegistrantName">Full Name</label>
                        <input type="text" id="modalRegistrantName" placeholder="Enter registrant full name">
                        <div class="modal-help">Example: Amina Yusuf</div>
                    </div>
                    
                    <div class="modal-field">
                        <label for="modalRegistrantEmail">Email Address</label>
                        <input type="email" id="modalRegistrantEmail" placeholder="Enter email address">
                        <div class="modal-help">Example: amina@example.com</div>
                    </div>
                    
                    <div class="modal-field">
                        <label for="modalRegistrantPhone">Phone Number</label>
                        <input type="tel" id="modalRegistrantPhone" placeholder="Enter phone number">
                        <div class="modal-help">Example: 0712345678</div>
                    </div>
                    
                    <div class="modal-field">
                        <label for="modalAccessCode">Access Code (Optional)</label>
                        <input type="text" id="modalAccessCode" placeholder="Leave blank to auto-generate">
                        <div class="modal-help">If empty, system will create a unique code automatically</div>
                    </div>
                </div>
            </div>
            <div class="modal-actions">
                <button class="modal-btn modal-btn-secondary" id="cancelModal">Cancel</button>
                <button class="modal-btn modal-btn-secondary" id="addAnotherRegistrant">+ Add Another</button>
                <button class="modal-btn modal-btn-primary" id="saveRegistrants">Save All Registrants</button>
            </div>
            
            <!-- Current Registrants List -->
            <div class="modal-registrants-list" id="registrantsList" style="margin-top: 20px; display: none;">
                <h4 style="margin: 0 0 12px 0; font-size: 16px; color: #374151;">Current Registrants:</h4>
                <div id="registrantsListContent"></div>
            </div>
        </div>
    </div>

    <input type="hidden" id="registrant_list" name="registrant_list" value="<?php echo h($_POST['registrant_list'] ?? ''); ?>">
<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log("DOM loaded, initializing map...");
    
    // Simple map initialization
    const findButton = document.getElementById("findLocation");
    const locationButton = document.getElementById("useMyLocation");
    const latInput = document.getElementById("location_lat");
    const lngInput = document.getElementById("location_lng");
    const status = document.getElementById("locationStatus");
    const locationSearch = document.getElementById("locationSearch");
    
    console.log("Elements found:", {
        findButton: !!findButton,
        locationButton: !!locationButton,
        latInput: !!latInput,
        lngInput: !!lngInput,
        status: !!status,
        locationSearch: !!locationSearch
    });
    
    // Initialize map
    let map = null;
    let marker = null;
    
    if (document.getElementById("adminEventMap")) {
        console.log("Initializing Leaflet map...");
        map = L.map("adminEventMap").setView([-6.7924, 37.6601], 6);
        
        L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
            maxZoom: 19,
            attribution: "&copy; OpenStreetMap contributors"
        }).addTo(map);
        
        console.log("Map initialized successfully");
    } else {
        console.error("Map container not found!");
    }
    
    // Simple marker function
    function setMarker(lat, lng, zoom) {
        console.log("Setting marker at:", lat, lng);
        
        if (!map) {
            console.error("Map not initialized!");
            return;
        }
        
        if (marker) {
            marker.setLatLng([lat, lng]);
        } else {
            marker = L.marker([lat, lng]).addTo(map);
        }
        
        latInput.value = lat.toFixed(7);
        lngInput.value = lng.toFixed(7);
        
        if (zoom) {
            map.setView([lat, lng], zoom);
        }
        
        console.log("Marker set successfully");
    }
    
    // Find on Map button
    if (findButton) {
        findButton.addEventListener("click", function() {
            console.log("Find on Map clicked!");
            const query = locationSearch.value.trim();
            
            if (!query) {
                status.textContent = "Type a location name first.";
                return;
            }
            
            status.textContent = "Searching...";
            console.log("Searching for:", query);
            
            fetch("https://nominatim.openstreetmap.org/search?format=json&q=" + encodeURIComponent(query))
                .then(response => response.json())
                .then(data => {
                    console.log("Search results:", data);
                    if (data && data.length > 0) {
                        const result = data[0];
                        setMarker(parseFloat(result.lat), parseFloat(result.lon), 15);
                        status.textContent = "Location found: " + result.display_name;
                    } else {
                        status.textContent = "Location not found. Try another name.";
                    }
                })
                .catch(error => {
                    console.error("Search error:", error);
                    status.textContent = "Search failed. Check internet connection.";
                });
        });
    }
    
    // Use My Location button
    if (locationButton) {
        locationButton.addEventListener("click", function() {
            console.log("Use My Location clicked!");
            
            if (!navigator.geolocation) {
                status.textContent = "Geolocation not supported.";
                return;
            }
            
            status.textContent = "Getting your location...";
            
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    console.log("Location found:", position.coords);
                    setMarker(position.coords.latitude, position.coords.longitude, 15);
                    status.textContent = "Location set to your current position.";
                },
                function(error) {
                    console.error("Geolocation error:", error);
                    status.textContent = "Could not get your location. Allow location access.";
                }
            );
        });
    }
    
    // Map click to set location
    if (map) {
        map.on("click", function(e) {
            console.log("Map clicked at:", e.latlng);
            setMarker(e.latlng.lat, e.latlng.lng, 15);
            status.textContent = "Location selected on map.";
        });
    }
    
    // Load existing location if any
    const existingLat = parseFloat(latInput.value);
    const existingLng = parseFloat(lngInput.value);
    if (!isNaN(existingLat) && !isNaN(existingLng)) {
        setMarker(existingLat, existingLng, 15);
    }
    
    console.log("Map functionality initialized");
});
</script>
<?php renderAppShellEnd("create-event"); ?>
