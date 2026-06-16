namespace RosiPrintAgent;

/// <summary>Einheitliche API-Antwort der ROSI-API: { success, message, data }.</summary>
public class ApiResponse<T>
{
    public bool Success { get; set; }
    public string? Message { get; set; }
    public T? Data { get; set; }
}

public class HeartbeatData
{
    public string? Agent { get; set; }
    public string? Station { get; set; }
    public int Pending { get; set; }
}

public class ClaimData
{
    public List<PrintJobDto> Jobs { get; set; } = new();
}

public class PrintJobDto
{
    public string Id { get; set; } = "";
    public string? JobType { get; set; }
    public string? Reference { get; set; }
    public string? PrinterName { get; set; }
    public string? CreatedBy { get; set; }
    public bool Pace { get; set; }
    public List<LabelDto> Labels { get; set; } = new();
}

public class LabelDto
{
    public string Xml { get; set; } = "";
}
