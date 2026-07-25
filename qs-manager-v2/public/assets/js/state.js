
export const state = {
  services: [],
  servicesSort: {
    key: 'name',
    direction: 'asc',
  },
  staff: [],
  bookings: [],
  bitacoras: [],
  bookingsView: 'upcoming',
  activeTab: 'services',
  bookingsPagination: {
    currentPage: 1,
    perPage: 10,
  },
  bookingsSort: {
    key: 'scheduled_for',
    direction: 'asc',
  }
};
