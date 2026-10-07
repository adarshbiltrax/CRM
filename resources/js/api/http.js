import axios from 'axios'

const http = axios.create({
  baseURL: '/api',
  withCredentials: true,
  headers: {
    Accept: 'application/json',
  },
})

http.interceptors.request.use((config) => {
  const token = sessionStorage.getItem('srm_access_token')
  const csrfToken = sessionStorage.getItem('srm_csrf_token')

  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }

  if (csrfToken && ['post', 'put', 'patch', 'delete'].includes(config.method?.toLowerCase())) {
    config.headers['X-XSRF-TOKEN'] = csrfToken
  }

  return config
})

http.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      sessionStorage.removeItem('srm_access_token')
      sessionStorage.removeItem('srm_user')
      sessionStorage.removeItem('srm_auth_mode')
      window.dispatchEvent(new Event('srm:unauthorized'))
    }

    return Promise.reject(error)
  },
)

export default http
