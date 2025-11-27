export function useFormatters() {
  const formatCurrency = (amount: number | string): string => {
    const numericAmount = typeof amount === 'string' ? parseFloat(amount) : amount
    if (isNaN(numericAmount)) return '₱0.00'
    return new Intl.NumberFormat('en-PH', {
      style: 'currency',
      currency: 'PHP',
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(numericAmount)
  }

  const formatDate = (date: string | Date | null, format: 'short' | 'long' = 'long') => {
    if (!date) return 'N/A';
    
    // ✅ Handle both ISO strings and Date objects
    const dateObj = typeof date === 'string' ? new Date(date) : date;
    
    if (format === 'short') {
      return dateObj.toLocaleDateString('en-US', { 
        month: 'short', 
        day: 'numeric', 
        year: 'numeric' 
      });
    }
    
    return dateObj.toLocaleDateString('en-US', { 
      year: 'numeric',
      month: 'long',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  };

  const formatDateTime = (date: string | Date) => {
    if (!date) return { date: 'N/A', time: 'N/A' }
    const dateObj = typeof date === 'string' ? new Date(date) : date
    if (isNaN(dateObj.getTime())) return { date: 'Invalid Date', time: 'Invalid Time' }
    return {
      date: dateObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }),
      time: dateObj.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }),
    }
  }

  const getRelativeTime = (date: string | Date): string => {
    if (!date) return 'Unknown'
    const dateObj = typeof date === 'string' ? new Date(date) : date
    if (isNaN(dateObj.getTime())) return 'Invalid Date'
    const now = new Date()
    const diffInSeconds = Math.floor((now.getTime() - dateObj.getTime()) / 1000)
    if (diffInSeconds < 60) return 'Just now'
    if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)} minutes ago`
    if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)} hours ago`
    if (diffInSeconds < 604800) return `${Math.floor(diffInSeconds / 86400)} days ago`
    return formatDate(dateObj, 'short')
  }

  const formatNumber = (value: number | string): string => {
    const numericValue = typeof value === 'string' ? parseFloat(value) : value
    if (isNaN(numericValue)) return '0'
    return new Intl.NumberFormat('en-US').format(numericValue)
  }

  const formatPercentage = (value: number, decimals: number = 2): string => {
    if (isNaN(value)) return '0%'
    return `${value.toFixed(decimals)}%`
  }

  return {
    formatCurrency,
    formatDate,
    formatDateTime,
    getRelativeTime,
    formatNumber,
    formatPercentage,
  }
}