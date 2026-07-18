
export const state = {
  services: [],
  servicesSort: {
    key: 'name',
    direction: 'asc',
  },
  staff: [],
  bookings: [],
  activeTab: 'services',
  bookingsPagination: {
    currentPage: 1,
    perPage: 10,
  },
  bookingsSort: {
    key: 'scheduled_for',
    direction: 'desc',
  }
};
