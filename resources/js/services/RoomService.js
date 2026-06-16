// src/services/RoomService.js (New file)
import { ref } from 'vue';

// --- Simulated Database State (based on your roomList in Roomlayout.vue) ---
// In a real application, this data would come from API calls to a backend.
const initialRoomData = [
    { id: 1, room: 'Room 101', building: 'Admin Building', college: 'College of Business Admin', capacity: 40, location: '1st Floor, North', roomType: 'Lecture Hall' },
    { id: 2, room: 'Room 202', building: 'Science Hall', college: 'College of Science', capacity: 60, location: '2nd Floor, East', roomType: 'Classroom' },
    { id: 3, room: 'Room 305', building: 'IT Building', college: 'College of Computer Studies', capacity: 35, location: '3rd Floor, West', roomType: 'Computer Lab' },
    { id: 4, room: 'Room 401', building: 'Main Building', college: 'College of Engineering', capacity: 80, location: '4th Floor, South', roomType: 'Lecture Hall' },
    { id: 5, room: 'Conf Rm 2', building: 'Admin Building', college: 'College of Social Sciences', capacity: 20, location: '2nd Floor, South', roomType: 'Conference Room' },
    { id: 6, room: 'Lab 102', building: 'Science Hall', college: 'College of Science', capacity: 25, location: 'Ground Floor, East', roomType: 'Science Lab' },
    { id: 7, room: 'Study Rm A', building: 'Library', college: 'College of Arts & Letters', capacity: 12, location: 'Ground Floor, West', roomType: 'Study Area' },
];

// The primary state managed by the service
const roomList = ref(initialRoomData);

// --- CRUD Operations (Service Methods) ---

/**
 * Simulates fetching all rooms (SELECT * FROM rooms).
 * @returns {Ref<Array<Object>>} The reactive list of rooms.
 */
const fetchAllRooms = () => {
    // In a real app: return api.get('/rooms').then(res => res.data);
    return roomList; // Return the reactive ref for live updates
};

/**
 * Simulates adding a new room (INSERT INTO rooms).
 * @param {Object} newRoomData - Data for the new room.
 */
const addRoom = (newRoomData) => {
    // 1. Generate new ID
    const newId = roomList.value.length > 0
        ? Math.max(...roomList.value.map(r => r.id)) + 1
        : 1;

    // 2. Create the room object
    const newRoom = {
        id: newId,
        ...newRoomData,
        capacity: Number(newRoomData.capacity) // Ensure capacity is a number
    };

    // 3. Add to the state
    roomList.value.push(newRoom);
    console.log(`[Service] New room ${newRoom.room} added.`);
    // In a real app: api.post('/rooms', newRoomData);
};

/**
 * Simulates updating an existing room (UPDATE rooms).
 * @param {Object} updatedRoom - The room object with updated data.
 */
const updateRoom = (updatedRoom) => {
    const index = roomList.value.findIndex(r => r.id === updatedRoom.id);

    if (index !== -1) {
        // Replace the old object with the new one
        roomList.value[index] = updatedRoom;
        console.log(`[Service] Room ${updatedRoom.room} updated.`);
        // In a real app: api.put(`/rooms/${updatedRoom.id}`, updatedRoom);
    } else {
        console.error(`[Service] Room with ID ${updatedRoom.id} not found for update.`);
    }
};

/**
 * Simulates deleting a room (DELETE FROM rooms).
 * @param {number} id - The ID of the room to delete.
 */
const deleteRoom = (id) => {
    roomList.value = roomList.value.filter(room => room.id !== id);
    console.log(`[Service] Room with ID ${id} deleted.`);
    // In a real app: api.delete(`/rooms/${id}`);
};

// Expose the reactive state and service methods
export default {
    fetchAllRooms,
    addRoom,
    updateRoom,
    deleteRoom,
    // Note: roomList itself is not directly exposed to enforce data integrity via methods
};