// Date creation helper
export const createDate = (dateStr, timeStr) => {
  if (timeStr) {
    const [h, m] = timeStr.split(':').map(Number);
    const [y, M, d] = dateStr.split('-').map(Number);
    return new Date(y, M - 1, d, h, m);
  }
  const [y, M, d] = dateStr.split('-').map(Number);
  return new Date(y, M - 1, d);
};

// Load events from localStorage
export const loadEventsFromStorage = () => {
  const saved = localStorage.getItem('scheduleEvents');
  if (saved) {
    try {
      const parsed = JSON.parse(saved);
      return parsed.map(event => ({
        ...event,
        start: new Date(event.start),
        end: event.end ? new Date(event.end) : null
      }));
    } catch (e) {
      console.error('Failed to load events:', e);
    }
  }
  return [
    {
      id: 1,
      title: 'Class: CS 101',
      list: 'Class',
      allDay: false,
      start: createDate('2025-11-15', '10:00'),
      end: createDate('2025-11-15', '12:00'),
      extendedProps: {
        room: 'UG 114',
        building: 'Engineering',
        college: 'CompSci',
        subject: 'Class: CS 101',
        type: 'Class',
        requester: 'Prof. John Smith',
        description: 'Introduction to Computer Science',
        agenda: '',
        title: 'Class: CS 101',
        organizer: '',
        name: '',
        deptOffice: 'CompSci',
        organization: 'Engineering',
        isHoliday: false,
        recurring: false,
        numberParticipants: 40,
        equipment: ['Chairs', 'Table', 'Remote Control'],
        tablesChairs: true,
        airConditioner: true,
        whiteboard: true,
        additionalInstructions: 'Please prepare materials',
        driveLink: '',
        faculty: 'Prof. John Smith',
        section: 'CS101-01',
        numberOfStudents: 40
      }
    }
  ];
};

// Save events to localStorage
export const saveEventsToStorage = (events) => {
  const eventsToSave = events.map(event => ({
    ...event,
    start: event.start.toISOString(),
    end: event.end ? event.end.toISOString() : null
  }));
  localStorage.setItem('scheduleEvents', JSON.stringify(eventsToSave));
};

// Date utilities
export const dateToIsoDateString = (date) => {
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, '0');
  const d = String(date.getDate()).padStart(2, '0');
  return `${y}-${m}-${d}`;
};

export const formatTimeDisplay = (date) => {
  const d = new Date(date);
  return d.toLocaleTimeString('en-US', {
    hour: '2-digit',
    minute: '2-digit',
    hour12: true
  });
};

export const formatDateDisplay = (date) => {
  const d = new Date(date);
  return d.toLocaleDateString('en-US', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  });
};

// Extract ALL extended properties from form data
export const extractExtendedProps = (data) => ({
  // Room & Location
  room: data.room || 'N/A',
  building: data.organization || 'N/A',
  college: data.deptOffice || 'N/A',

  // Appointment Details
  subject: data.subject || data.title || data.agenda || data.name || 'Untitled',
  type: data.type || data.list || 'Event',
  requester: data.requester || 'N/A',
  description: data.description || '',
  agenda: data.agenda || '',
  title: data.title || '',
  organizer: data.organizer || '',
  name: data.name || '',

  // Class-specific
  numberOfStudents: data.numberOfStudents || null,
  faculty: data.faculty || '',
  section: data.section || '',

  // Event/Activity-specific
  numberParticipants: data.numberParticipants || null,

  // General Info
  deptOffice: data.deptOffice || '',
  organization: data.organization || '',
  isHoliday: data.isHoliday || false,
  recurring: data.recurring || false,

  // Equipment
  tablesChairs: data.tablesChairs || false,
  airConditioner: data.airConditioner || false,
  whiteboard: data.whiteboard || false,

  // Additional
  additionalInstructions: data.additionalInstructions || '',
  driveLink: data.driveLink || ''
});

// Occupancy checking
export const checkTimeSlotOccupancy = (room, startTime, endTime, events, excludeEventId = null) => {
  const start = new Date(startTime);
  const end = new Date(endTime);

  return events.some(event => {
    if (excludeEventId && event.id === excludeEventId) return false;
    if (event.extendedProps?.room !== room) return false;

    const eventStart = new Date(event.start);
    const eventEnd = event.end ? new Date(event.end) : new Date(eventStart.getTime() + 60 * 60000);
    return (start < eventEnd && end > eventStart);
  });
};

// Get available slots
export const getAvailableSlotsForRoom = (room, date, events, durationHours = 1) => {
  const allSlots = [];
  const dayStart = new Date(date);
  dayStart.setHours(6, 0, 0, 0);

  const dayEnd = new Date(date);
  dayEnd.setHours(22, 0, 0, 0);

  const roomEvents = events.filter(event => {
    const eventDate = dateToIsoDateString(new Date(event.start));
    const targetDate = dateToIsoDateString(new Date(date));
    return event.extendedProps?.room === room && eventDate === targetDate;
  }).sort((a, b) => new Date(a.start) - new Date(b.start));

  let currentTime = new Date(dayStart);

  while (currentTime < dayEnd) {
    const slotEnd = new Date(currentTime);
    slotEnd.setHours(currentTime.getHours() + durationHours);

    const isOccupied = roomEvents.some(event => {
      const eventStart = new Date(event.start);
      const eventEnd = event.end ? new Date(event.end) : new Date(eventStart.getTime() + 60 * 60000);
      return (currentTime < eventEnd && slotEnd > eventStart);
    });

    if (!isOccupied && slotEnd <= dayEnd) {
      allSlots.push({
        start: new Date(currentTime),
        end: new Date(slotEnd),
        display: `${formatTimeDisplay(currentTime)} - ${formatTimeDisplay(slotEnd)}`
      });
    }

    currentTime.setMinutes(currentTime.getMinutes() + 30);
  }

  return allSlots;
};
