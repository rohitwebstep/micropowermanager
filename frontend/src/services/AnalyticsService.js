import AnalyticsRepository from "@/repositories/AnalyticsRepository"

export const AnalyticsService = {

  async getAnalytics(filters) {
    const response = await AnalyticsRepository.fetchAnalytics(filters)
    return response.data.data
  }

}
